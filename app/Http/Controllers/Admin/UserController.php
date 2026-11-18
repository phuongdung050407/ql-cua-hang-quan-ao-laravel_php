<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $users = User::query()
            ->when($request->filled('role'), fn ($q) => $q->where('role', $request->role))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = trim($request->q);
                $q->where('name', 'like', "%{$term}%")
                  ->orWhere('email', 'like', "%{$term}%");
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.users.index', compact('users'));
    }

    public function edit(User $user)
    {
        return view('admin.users.edit', compact('user'));
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name'    => ['required', 'string', 'max:255'],
            'phone'   => ['nullable', 'string', 'max:20'],
            'gender'  => ['nullable', 'in:male,female,other'],
            'address' => ['nullable', 'string'],
            'role'    => ['required', 'in:customer,staff,admin'],
        ]);

        // Ngăn Admin tự hạ quyền chính mình để tránh mất quyền quản trị hệ thống
        if ($user->id === Auth::id() && $data['role'] !== 'admin') {
            return back()->withErrors(['role' => 'Bạn không thể tự hạ quyền quản trị viên của chính mình.']);
        }

        $user->update($data);

        return redirect()->route('admin.users.index')->with('success', 'Cập nhật thông tin tài khoản thành công.');
    }

    public function destroy(User $user)
    {
        if ($user->id === Auth::id()) {
            return back()->with('error', 'Không thể xóa tài khoản đang đăng nhập.');
        }

        $user->delete();

        return redirect()->route('admin.users.index')->with('success', 'Đã xóa tài khoản người dùng.');
    }
}