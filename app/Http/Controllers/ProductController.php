<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::active()->with(['category', 'variants']);

        if ($request->filled('gender') && in_array($request->gender, ['male', 'female', 'unisex'], true)) {
            $query->where('gender', $request->gender);
        }

        if ($request->filled('color')) {
            $color = trim($request->color);
            $query->whereHas('variants', fn ($q) => $q->where('color', 'like', "%{$color}%"));
        }

        if ($request->filled('min_price')) {
            $query->where('base_price', '>=', (float) $request->min_price);
        }

        if ($request->filled('max_price')) {
            $query->where('base_price', '<=', (float) $request->max_price);
        }

        match ($request->get('sort')) {
            'price_asc'  => $query->orderBy('base_price', 'asc'),
            'price_desc' => $query->orderBy('base_price', 'desc'),
            default      => $query->latest(),
        };

        $products = $query->paginate(12)->withQueryString();
        $categories = Category::where('is_active', true)->whereNull('parent_id')->with('children')->get();

        return view('storefront.products.index', compact('products', 'categories'));
    }

    public function byCategory(string $slug)
    {
        $category = Category::where('slug', $slug)->where('is_active', true)->firstOrFail();

        $categoryIds = $category->children()->pluck('id')->push($category->id);

        $products = Product::active()
            ->with(['category', 'variants'])
            ->whereIn('category_id', $categoryIds)
            ->latest()
            ->paginate(12);

        $categories = Category::where('is_active', true)->whereNull('parent_id')->with('children')->get();

        return view('storefront.products.index', compact('products', 'categories', 'category'));
    }

    public function show(string $slug)
    {
        $product = Product::active()
            ->with(['category', 'variants'])
            ->where('slug', $slug)
            ->firstOrFail();

        // Lấy danh sách màu và size duy nhất để hiển thị nút chọn trên giao diện
        $availableColors = $product->variants->pluck('color')->unique()->values();
        $availableSizes  = $product->variants->pluck('size')->unique()->values();

        // Dữ liệu JSON biến thể để JS kiểm tra tồn kho và giá khi khách bấm chọn Màu + Size
        $variantsMap = $product->variants->map(fn ($v) => [
            'id'             => $v->id,
            'sku'            => $v->sku,
            'color'          => $v->color,
            'size'           => $v->size,
            'price'          => (float) $v->effective_price,
            'stock_quantity' => $v->stock_quantity,
            'image'          => $v->image ? asset('storage/' . $v->image) : null,
        ]);

        $relatedProducts = Product::active()
            ->with('variants')
            ->where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->take(4)
            ->get();

        return view('storefront.products.show', compact(
            'product',
            'availableColors',
            'availableSizes',
            'variantsMap',
            'relatedProducts'
        ));
    }
}