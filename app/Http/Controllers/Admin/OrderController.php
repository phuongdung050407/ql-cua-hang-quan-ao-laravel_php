<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $orders = Order::with('user')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = trim($request->q);
                $q->where('order_code', 'like', "%{$term}%")
                  ->orWhere('customer_name', 'like', "%{$term}%")
                  ->orWhere('customer_phone', 'like', "%{$term}%");
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.orders.index', compact('orders'));
    }

    public function show(Order $order)
    {
        $order->load(['user', 'items.variant.product']);

        return view('admin.orders.show', compact('order'));
    }

    public function updateStatus(Request $request, Order $order)
    {
        $data = $request->validate([
            'status'         => ['required', 'in:pending,confirmed,shipping,completed,cancelled'],
            'payment_status' => ['required', 'in:unpaid,paid,refunded'],
        ]);

        if ($order->status === 'cancelled' && $data['status'] !== 'cancelled') {
            return back()->with('error', 'Đơn hàng đã hủy không thể khôi phục lại trạng thái xử lý.');
        }

        DB::transaction(function () use ($order, $data) {
            // Nếu đơn hàng chuyển sang trạng thái hủy, hoàn trả lại số lượng tồn kho cho biến thể
            if ($order->status !== 'cancelled' && $data['status'] === 'cancelled') {
                $order->load('items.variant');
                foreach ($order->items as $item) {
                    $item->variant?->increment('stock_quantity', $item->quantity);
                }
            }

            $order->update([
                'status'         => $data['status'],
                'payment_status' => $data['payment_status'],
            ]);
        });

        return back()->with('success', 'Cập nhật trạng thái đơn hàng thành công.');
    }
}