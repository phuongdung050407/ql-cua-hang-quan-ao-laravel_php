<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\SearchHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $totalRevenue = Order::where('status', 'completed')->sum('total_amount');
        $totalOrders = Order::count();
        $pendingOrders = Order::where('status', 'pending')->count();
        $totalProducts = Product::count();
        $totalCustomers = User::where('role', 'customer')->count();

        // Top 10 từ khóa được khách hàng tìm kiếm nhiều nhất
        $topKeywords = SearchHistory::select('normalized_keyword', DB::raw('MAX(keyword) as sample_keyword'), DB::raw('COUNT(*) as search_count'))
            ->groupBy('normalized_keyword')
            ->orderByDesc('search_count')
            ->take(10)
            ->get();

        // Top từ khóa không có kết quả (giúp chủ shop biết khách đang tìm món gì mà shop chưa nhập)
        $zeroResultKeywords = SearchHistory::where('results_count', 0)
            ->select('normalized_keyword', DB::raw('MAX(keyword) as sample_keyword'), DB::raw('COUNT(*) as search_count'))
            ->groupBy('normalized_keyword')
            ->orderByDesc('search_count')
            ->take(5)
            ->get();

        $recentOrders = Order::latest()->take(6)->get();

        return view('admin.dashboard', compact(
            'totalRevenue',
            'totalOrders',
            'pendingOrders',
            'totalProducts',
            'totalCustomers',
            'topKeywords',
            'zeroResultKeywords',
            'recentOrders'
        ));
    }
}