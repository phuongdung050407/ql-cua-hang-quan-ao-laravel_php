<?php

namespace App\Services;

use App\Models\Product;
use App\Models\SearchHistory;
use App\Models\SearchProductClick;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ProductRecommendationService
{
    /**
     * Lấy danh sách sản phẩm gợi ý cá nhân hóa dựa trên lịch sử tìm kiếm và lượt click
     */
    public function getRecommendations(?User $user, string $sessionId, int $limit = 8): Collection
    {
        // 1. Lấy 20 truy vấn tìm kiếm gần nhất trong 30 ngày qua
        $histories = SearchHistory::query()
            ->when(
                $user,
                fn ($q) => $q->where('user_id', $user->id),
                fn ($q) => $q->whereNull('user_id')->where('session_id', $sessionId)
            )
            ->where('created_at', '>=', now()->subDays(30))
            ->latest()
            ->take(20)
            ->get();

        // Trường hợp khách mới chưa có lịch sử tìm kiếm (Cold-start)
        if ($histories->isEmpty()) {
            return $this->getFallbackProducts($user, $limit);
        }

        // 2. Tính trọng số từ khóa (từ khóa mới hơn có điểm cao hơn)
        $keywordWeights = [];
        $categoryWeights = [];

        foreach ($histories as $history) {
            $daysAgo = max(0, $history->created_at->diffInDays(now()));
            $recencyWeight = 1 / (1 + $daysAgo * 0.1); // Suy giảm theo thời gian

            $kw = $history->normalized_keyword;
            if ($kw !== '') {
                $keywordWeights[$kw] = ($keywordWeights[$kw] ?? 0) + $recencyWeight;
            }

            if ($history->matched_category_id) {
                $catId = $history->matched_category_id;
                $categoryWeights[$catId] = ($categoryWeights[$catId] ?? 0) + ($recencyWeight * 1.5);
            }
        }

        // 3. Cộng thêm trọng số từ các sản phẩm khách đã thực sự bấm vào xem (Implicit Feedback)
        $clickedProducts = SearchProductClick::with('product')
            ->whereIn('search_history_id', $histories->pluck('id'))
            ->get();

        $clickedProductIds = [];
        $preferredTags = [];

        foreach ($clickedProducts as $click) {
            if (!$click->product) {
                continue;
            }

            $clickedProductIds[] = $click->product_id;
            $catId = $click->product->category_id;
            $categoryWeights[$catId] = ($categoryWeights[$catId] ?? 0) + 3.0;

            if (is_array($click->product->tags)) {
                foreach ($click->product->tags as $tag) {
                    $normalizedTag = Str::ascii(mb_strtolower(trim($tag), 'UTF-8'));
                    $preferredTags[$normalizedTag] = ($preferredTags[$normalizedTag] ?? 0) + 2.0;
                }
            }
        }

        // 4. Lấy tập ứng viên sản phẩm đang kinh doanh và chấm điểm từng sản phẩm
        $candidates = Product::active()
            ->with(['category', 'variants'])
            ->whereHas('variants', fn ($q) => $q->where('stock_quantity', '>', 0))
            ->get();

        $scoredProducts = $candidates->map(function (Product $product) use (
            $keywordWeights,
            $categoryWeights,
            $clickedProductIds,
            $preferredTags,
            $user
        ) {
            $score = 0.0;

            $searchableText = Str::ascii(mb_strtolower(
                $product->name . ' ' . ($product->material ?? '') . ' ' . implode(' ', $product->tags ?? []),
                'UTF-8'
            ));

            // Quét thêm tên màu sắc nhập tự do từ các biến thể của sản phẩm
            $variantColors = Str::ascii(mb_strtolower(
                $product->variants->pluck('color')->implode(' '),
                'UTF-8'
            ));

            // Điểm khớp từ khóa tìm kiếm (tên, chất liệu, tag, màu sắc)
            foreach ($keywordWeights as $kw => $weight) {
                if (str_contains($searchableText, $kw)) {
                    $score += $weight * 3.0;
                }
                if (str_contains($variantColors, $kw)) {
                    $score += $weight * 2.5;
                }
            }

            // Điểm khớp danh mục quan tâm
            if (isset($categoryWeights[$product->category_id])) {
                $score += $categoryWeights[$product->category_id] * 2.0;
            }

            // Điểm khớp thẻ đặc trưng (tags) từ các sản phẩm đã click
            if (is_array($product->tags)) {
                foreach ($product->tags as $tag) {
                    $normalizedTag = Str::ascii(mb_strtolower(trim($tag), 'UTF-8'));
                    if (isset($preferredTags[$normalizedTag])) {
                        $score += $preferredTags[$normalizedTag];
                    }
                }
            }

            // Điểm cho sản phẩm từng click quan tâm
            if (in_array($product->id, $clickedProductIds, true)) {
                $score += 2.0;
            }

            // Ưu tiên ngữ cảnh giới tính tài khoản khách hàng
            if ($user && in_array($user->gender, ['male', 'female'], true)) {
                if ($product->gender === $user->gender) {
                    $score += 1.5;
                } elseif ($product->gender === 'unisex') {
                    $score += 0.5;
                }
            }

            $product->recommendation_score = $score;
            return $product;
        });

        // Lọc các sản phẩm có điểm > 0 và sắp xếp giảm dần
        $recommended = $scoredProducts
            ->filter(fn (Product $p) => $p->recommendation_score > 0)
            ->sortByDesc('recommendation_score')
            ->take($limit)
            ->values();

        // Bổ sung thêm sản phẩm mới nếu chưa đủ số lượng hiển thị trên giao diện
        if ($recommended->count() < $limit) {
            $excludeIds = $recommended->pluck('id')->all();
            $extra = $this->getFallbackProducts($user, $limit - $recommended->count(), $excludeIds);
            $recommended = $recommended->concat($extra);
        }

        return $recommended;
    }

    /**
     * Sản phẩm mặc định cho khách mới chưa có lịch sử tìm kiếm
     */
    private function getFallbackProducts(?User $user, int $limit, array $excludeIds = []): Collection
    {
        return Product::active()
            ->with(['category', 'variants'])
            ->whereNotIn('id', $excludeIds)
            ->whereHas('variants', fn ($q) => $q->where('stock_quantity', '>', 0))
            ->when($user && in_array($user->gender, ['male', 'female'], true), function ($q) use ($user) {
                $q->orderByRaw("CASE WHEN gender = ? THEN 0 WHEN gender = 'unisex' THEN 1 ELSE 2 END", [$user->gender]);
            })
            ->latest()
            ->take($limit)
            ->get();
    }
}