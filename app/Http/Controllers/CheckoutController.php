<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\Order;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CheckoutController extends Controller
{
    public function index()
    {
        $cart = Cart::with('items.variant.product')
            ->where('user_id', Auth::id())
            ->first();

        if (!$cart || $cart->items->isEmpty()) {
            return redirect()->route('cart.index')->with('error', 'Giỏ hàng của bạn đang trống.');
        }

        $totalAmount = $cart->items->sum(
            fn ($item) => (float) $item->variant->effective_price * $item->quantity
        );

        $user = Auth::user();

        return view('storefront.checkout.index', compact('cart', 'totalAmount', 'user'));
    }

    public function process(Request $request)
    {
        $data = $request->validate([
            'customer_name'    => ['required', 'string', 'max:255'],
            'customer_phone'   => ['required', 'string', 'max:20'],
            'shipping_address' => ['required', 'string'],
            'payment_method'   => ['required', 'in:cod,vnpay,momo,bank_transfer'],
            'note'             => ['nullable', 'string'],
        ]);

        $cart = Cart::with('items.variant.product')
            ->where('user_id', Auth::id())
            ->first();

        if (!$cart || $cart->items->isEmpty()) {
            return redirect()->route('cart.index')->with('error', 'Giỏ hàng của bạn đang trống.');
        }

        try {
            $order = DB::transaction(function () use ($data, $cart) {
                $totalAmount = 0;
                $orderItemsPayload = [];

                foreach ($cart->items as $item) {
                    // Khóa dòng biến thể để chống đặt vượt tồn kho khi có nhiều khách mua cùng lúc
                    $variant = ProductVariant::with('product')
                        ->where('id', $item->product_variant_id)
                        ->lockForUpdate()
                        ->firstOrFail();

                    if ($variant->stock_quantity < $item->quantity) {
                        throw new \RuntimeException(
                            "Sản phẩm \"{$variant->product->name}\" ({$variant->color} - {$variant->size}) không đủ tồn kho."
                        );
                    }

                    $unitPrice = (float) $variant->effective_price;
                    $totalAmount += $unitPrice * $item->quantity;

                    $orderItemsPayload[] = [
                        'product_variant_id' => $variant->id,
                        'quantity'           => $item->quantity,
                        'unit_price'         => $unitPrice,
                    ];

                    // Trừ tồn kho trực tiếp trên biến thể
                    $variant->decrement('stock_quantity', $item->quantity);
                }

                $order = Order::create([
                    'order_code'       => 'ORD-' . now()->format('Ymd') . '-' . Str::upper(Str::random(5)),
                    'user_id'          => Auth::id(),
                    'customer_name'    => $data['customer_name'],
                    'customer_phone'   => $data['customer_phone'],
                    'shipping_address' => $data['shipping_address'],
                    'total_amount'     => $totalAmount,
                    'status'           => 'pending',
                    'payment_method'   => $data['payment_method'],
                    'payment_status'   => 'unpaid',
                    'note'             => $data['note'] ?? null,
                ]);

                $order->items()->createMany($orderItemsPayload);
                $cart->items()->delete();

                return $order;
            });

            return redirect()
                ->route('customer.orders.show', $order->order_code)
                ->with('success', 'Đặt hàng thành công! Mã đơn hàng của bạn là ' . $order->order_code);
        } catch (\RuntimeException $e) {
            return redirect()->route('cart.index')->with('error', $e->getMessage());
        }
    }
}