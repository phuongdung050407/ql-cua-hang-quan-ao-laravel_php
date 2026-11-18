<?php

namespace App\Http\Controllers;

use App\Models\SearchHistory;
use Illuminate\Support\Facades\Auth;

class SearchHistoryController extends Controller
{
    public function index()
    {
        $histories = SearchHistory::with(['matchedCategory', 'clicks.product'])
            ->where('user_id', Auth::id())
            ->latest()
            ->paginate(15);

        return view('storefront.search-history.index', compact('histories'));
    }

    public function destroy(SearchHistory $searchHistory)
    {
        abort_if($searchHistory->user_id !== Auth::id(), 403);

        $searchHistory->delete();

        return back()->with('success', 'Đã xóa từ khóa khỏi lịch sử tìm kiếm.');
    }

    public function clear()
    {
        SearchHistory::where('user_id', Auth::id())->delete();

        return back()->with('success', 'Đã xóa toàn bộ lịch sử tìm kiếm.');
    }
}