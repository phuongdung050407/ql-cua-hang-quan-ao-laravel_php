<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\SearchHistory;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function showLoginForm()
    {
        return view('storefront.auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        // Lưu lại session_id trước khi regenerate session để đồng bộ lịch sử tìm kiếm & giỏ hàng
        $oldSessionId = $request->session()->getId();

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();
            $user = Auth::user();

            $this->syncGuestDataToUser($oldSessionId, $user->id);

            if (in_array($user->role, ['admin', 'staff'], true)) {
                return redirect()->intended(route('admin.dashboard'));
            }

            return redirect()->intended(route('home'));
        }

        return back()->withErrors([
            'email' => 'Email hoặc mật khẩu không chính xác.',
        ])->onlyInput('email');
    }

    public function showRegisterForm()
    {
        return view('storefront.auth.register');
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone'    => ['nullable', 'string', 'max:20'],
            'gender'   => ['nullable', 'in:male,female,other'],
            'address'  => ['nullable', 'string'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        $oldSessionId = $request->session()->getId();

        $user = User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'phone'    => $data['phone'] ?? null,
            'gender'   => $data['gender'] ?? null,
            'address'  => $data['address'] ?? null,
            'password' => Hash::make($data['password']),
            'role'     => 'customer',
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        $this->syncGuestDataToUser($oldSessionId, $user->id);

        return redirect()->route('home')->with('success', 'Đăng ký tài khoản thành công!');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    /**
     * Đồng bộ lịch sử tìm kiếm và giỏ hàng khi khách vãng lai đăng nhập/đăng ký
     */
    private function syncGuestDataToUser(string $oldSessionId, int $userId): void
    {
        SearchHistory::whereNull('user_id')
            ->where('session_id', $oldSessionId)
            ->update(['user_id' => $userId]);

        $guestCart = Cart::with('items')->whereNull('user_id')->where('session_id', $oldSessionId)->first();
        if (!$guestCart) {
            return;
        }

        $userCart = Cart::firstOrCreate(['user_id' => $userId]);

        foreach ($guestCart->items as $guestItem) {
            $existingItem = $userCart->items()
                ->where('product_variant_id', $guestItem->product_variant_id)
                ->first();

            if ($existingItem) {
                $existingItem->increment('quantity', $guestItem->quantity);
            } else {
                $userCart->items()->create([
                    'product_variant_id' => $guestItem->product_variant_id,
                    'quantity'           => $guestItem->quantity,
                ]);
            }
        }

        $guestCart->delete();
    }
}