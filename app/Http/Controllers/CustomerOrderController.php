<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CustomerOrderController extends Controller
{
    public function index()
    {
        $orders = Order::where('user_id', Auth::id())
            ->latest()
            ->paginate(10);

        return view('storefront.orders.index', compact('orders'));
    }

    public function show(string $orderCode)
    {
        $order = Order::with('items.variant.product')
            ->where('user_id', Auth::id())
            ->where('order_code', $orderCode)
            ->firstOrFail();

        return view('storefront.orders.show', compact('order'));
    }

    public function cancel(string $orderCode)
    {
        $order = Order::with('items.variant')
            ->where('user_id', Auth::id())
            ->where('order_code', $orderCode)
            ->firstOrFail();

        if ($order->status !== 'pending') {
            return back()->with('error', 'Chỉ có thể hủy đơn hàng khi đang ở trạng thái chờ xác nhận.');
        }

        DB::transaction(function () use ($order) {
            // Hoàn lại tồn kho cho từng biến thể
            foreach ($order->items as $item) {
                $item->variant?->increment('stock_quantity', $item->quantity);
            }

            $order->update(['status' => 'cancelled']);
        });

        return back()->with('success', 'Đã hủy đơn hàng thành công.');
    }
}