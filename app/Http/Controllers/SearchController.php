<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\SearchHistory;
use App\Models\SearchProductClick;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class SearchController extends Controller
{
    public function index(Request $request)
    {
        $keyword = trim((string) $request->query('q', ''));

        if ($keyword === '') {
            return redirect()->route('products.index');
        }

        $normalizedKeyword = $this->normalizeKeyword($keyword);

        // Tìm danh mục khớp với từ khóa để hỗ trợ mở rộng gợi ý
        $matchedCategory = Category::where('is_active', true)
            ->get()
            ->first(function (Category $cat) use ($normalizedKeyword) {
                $catNorm = $this->normalizeKeyword($cat->name);
                return $catNorm !== '' && (str_contains($normalizedKeyword, $catNorm) || str_contains($catNorm, $normalizedKeyword));
            });

        // Truy vấn sản phẩm theo tên, mô tả, chất liệu, tags hoặc màu sắc biến thể
        $productsQuery = Product::active()
            ->with(['category', 'variants'])
            ->where(function ($q) use ($keyword) {
                $q->where('name', 'like', "%{$keyword}%")
                  ->orWhere('description', 'like', "%{$keyword}%")
                  ->orWhere('material', 'like', "%{$keyword}%")
                  ->orWhereJsonContains('tags', mb_strtolower($keyword, 'UTF-8'))
                  ->orWhereHas('variants', fn ($vq) => $vq->where('color', 'like', "%{$keyword}%"));
            });

        $products = $productsQuery->latest()->paginate(12)->withQueryString();

        // Lưu lịch sử tìm kiếm của khách hàng
        $searchHistory = SearchHistory::create([
            'user_id'             => Auth::id(),
            'session_id'          => $request->session()->getId(),
            'keyword'             => $keyword,
            'normalized_keyword'  => $normalizedKeyword,
            'matched_category_id' => $matchedCategory?->id,
            'results_count'       => $products->total(),
        ]);

        return view('storefront.search.index', compact('products', 'keyword', 'searchHistory'));
    }

    /**
     * Ghi nhận lượt click vào sản phẩm từ trang kết quả tìm kiếm rồi chuyển sang trang chi tiết
     */
    public function trackClick(SearchHistory $searchHistory, Product $product)
    {
        SearchProductClick::firstOrCreate([
            'search_history_id' => $searchHistory->id,
            'product_id'        => $product->id,
        ], [
            'user_id' => Auth::id() ?? $searchHistory->user_id,
        ]);

        return redirect()->route('products.show', $product->slug);
    }

    /**
     * Gợi ý nhanh từ khóa cũ khi khách đang gõ vào thanh tìm kiếm (AJAX)
     */
    public function autocomplete(Request $request)
    {
        $term = trim((string) $request->query('q', ''));
        $normalized = $this->normalizeKeyword($term);

        $recentKeywords = SearchHistory::query()
            ->when(
                Auth::check(),
                fn ($q) => $q->where('user_id', Auth::id()),
                fn ($q) => $q->where('session_id', $request->session()->getId())
            )
            ->when($normalized !== '', fn ($q) => $q->where('normalized_keyword', 'like', "%{$normalized}%"))
            ->select('keyword')
            ->distinct()
            ->latest('id')
            ->take(5)
            ->pluck('keyword');

        $suggestedProducts = $term !== ''
            ? Product::active()
                ->where('name', 'like', "%{$term}%")
                ->select('id', 'name', 'slug', 'base_price', 'thumbnail')
                ->take(5)
                ->get()
            : collect();

        return response()->json([
            'keywords' => $recentKeywords,
            'products' => $suggestedProducts,
        ]);
    }

    private function normalizeKeyword(string $keyword): string
    {
        $ascii = Str::ascii(mb_strtolower(trim($keyword), 'UTF-8'));
        return preg_replace('/\s+/', ' ', $ascii) ?? '';
    }
}