<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CartController extends Controller
{
    public function index(Request $request)
    {
        $cart = $this->getOrCreateCart($request);
        $cart->load(['items.variant.product']);

        $totalAmount = $cart->items->sum(function (CartItem $item) {
            return (float) $item->variant->effective_price * $item->quantity;
        });

        return view('storefront.cart.index', compact('cart', 'totalAmount'));
    }

    public function add(Request $request)
    {
        $data = $request->validate([
            'product_variant_id' => ['required', 'exists:product_variants,id'],
            'quantity'           => ['required', 'integer', 'min:1'],
        ]);

        $variant = ProductVariant::with('product')->findOrFail($data['product_variant_id']);

        if (!$variant->product->is_active || $variant->stock_quantity < $data['quantity']) {
            return back()->with('error', 'Biến thể sản phẩm này hiện không đủ số lượng trong kho.');
        }

        $cart = $this->getOrCreateCart($request);
        $cartItem = $cart->items()->where('product_variant_id', $variant->id)->first();

        $newQuantity = ($cartItem?->quantity ?? 0) + $data['quantity'];
        if ($newQuantity > $variant->stock_quantity) {
            return back()->with('error', "Kho chỉ còn tối đa {$variant->stock_quantity} sản phẩm cho phân loại này.");
        }

        if ($cartItem) {
            $cartItem->update(['quantity' => $newQuantity]);
        } else {
            $cart->items()->create([
                'product_variant_id' => $variant->id,
                'quantity'           => $data['quantity'],
            ]);
        }

        return redirect()->route('cart.index')->with('success', 'Đã thêm sản phẩm vào giỏ hàng.');
    }

    public function update(Request $request, CartItem $cartItem)
    {
        $cart = $this->getOrCreateCart($request);
        abort_if($cartItem->cart_id !== $cart->id, 403);

        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        if ($data['quantity'] > $cartItem->variant->stock_quantity) {
            return back()->with('error', "Số lượng vượt quá tồn kho ({$cartItem->variant->stock_quantity}).");
        }

        $cartItem->update(['quantity' => $data['quantity']]);

        return back()->with('success', 'Đã cập nhật số lượng giỏ hàng.');
    }

    public function remove(Request $request, CartItem $cartItem)
    {
        $cart = $this->getOrCreateCart($request);
        abort_if($cartItem->cart_id !== $cart->id, 403);

        $cartItem->delete();

        return back()->with('success', 'Đã xóa sản phẩm khỏi giỏ hàng.');
    }

    public function clear(Request $request)
    {
        $cart = $this->getOrCreateCart($request);
        $cart->items()->delete();

        return back()->with('success', 'Đã làm trống giỏ hàng.');
    }

    private function getOrCreateCart(Request $request): Cart
    {
        if (Auth::check()) {
            return Cart::firstOrCreate(['user_id' => Auth::id()]);
        }

        return Cart::firstOrCreate([
            'user_id'    => null,
            'session_id' => $request->session()->getId(),
        ]);
    }
}