<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $products = Product::with(['category', 'variants'])
            ->when($request->filled('q'), fn ($q) => $q->where('name', 'like', '%' . $request->q . '%'))
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('admin.products.index', compact('products'));
    }

    public function create()
    {
        $categories = Category::where('is_active', true)->get();

        return view('admin.products.create', compact('categories'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'category_id'               => ['required', 'exists:categories,id'],
            'name'                      => ['required', 'string', 'max:255'],
            'description'               => ['nullable', 'string'],
            'material'                  => ['nullable', 'string', 'max:100'],
            'gender'                    => ['required', 'in:male,female,unisex'],
            'base_price'                => ['required', 'numeric', 'min:0'],
            'tags'                      => ['nullable', 'string'], // Nhập dạng chuỗi cách nhau dấu phẩy: "oversize, mùa hè"
            'thumbnail'                 => ['nullable', 'image', 'max:2048'],
            'is_active'                 => ['nullable', 'boolean'],
            'variants'                  => ['required', 'array', 'min:1'],
            'variants.*.sku'            => ['required', 'string', 'max:50', 'distinct', 'unique:product_variants,sku'],
            'variants.*.color'          => ['required', 'string', 'max:50'],
            'variants.*.size'           => ['required', 'string', 'max:20'],
            'variants.*.price'          => ['nullable', 'numeric', 'min:0'],
            'variants.*.stock_quantity' => ['required', 'integer', 'min:0'],
            'variants.*.image'          => ['nullable', 'image', 'max:2048'],
        ]);

        // Kiểm tra trùng tổ hợp (Màu + Size) trong form gửi lên
        if ($this->hasDuplicateColorSize($data['variants'])) {
            return back()->withInput()->withErrors([
                'variants' => 'Các biến thể của cùng một sản phẩm không được trùng lặp tổ hợp Màu sắc và Kích cỡ.',
            ]);
        }

        DB::transaction(function () use ($request, $data) {
            $thumbnailPath = $request->hasFile('thumbnail')
                ? $request->file('thumbnail')->store('products', 'public')
                : null;

            $product = Product::create([
                'category_id' => $data['category_id'],
                'name'        => $data['name'],
                'slug'        => $this->generateUniqueSlug($data['name']),
                'description' => $data['description'] ?? null,
                'material'    => $data['material'] ?? null,
                'gender'      => $data['gender'],
                'base_price'  => $data['base_price'],
                'tags'        => $this->parseTags($data['tags'] ?? ''),
                'thumbnail'   => $thumbnailPath,
                'is_active'   => $request->boolean('is_active', true),
            ]);

            foreach ($data['variants'] as $index => $variantData) {
                $variantImagePath = $request->hasFile("variants.{$index}.image")
                    ? $request->file("variants.{$index}.image")->store('variants', 'public')
                    : null;

                // Mutator trong ProductVariant sẽ tự động chuẩn hóa color và size
                $product->variants()->create([
                    'sku'            => trim($variantData['sku']),
                    'color'          => $variantData['color'],
                    'size'           => $variantData['size'],
                    'price'          => $variantData['price'] ?? null,
                    'stock_quantity' => $variantData['stock_quantity'],
                    'image'          => $variantImagePath,
                ]);
            }
        });

        return redirect()->route('admin.products.index')->with('success', 'Thêm sản phẩm và các biến thể thành công.');
    }

    public function show(Product $product)
    {
        $product->load(['category', 'variants']);

        return view('admin.products.show', compact('product'));
    }

    public function edit(Product $product)
    {
        $product->load('variants');
        $categories = Category::where('is_active', true)->get();

        return view('admin.products.edit', compact('product', 'categories'));
    }

    public function update(Request $request, Product $product)
    {
        $data = $request->validate([
            'category_id'               => ['required', 'exists:categories,id'],
            'name'                      => ['required', 'string', 'max:255'],
            'description'               => ['nullable', 'string'],
            'material'                  => ['nullable', 'string', 'max:100'],
            'gender'                    => ['required', 'in:male,female,unisex'],
            'base_price'                => ['required', 'numeric', 'min:0'],
            'tags'                      => ['nullable', 'string'],
            'thumbnail'                 => ['nullable', 'image', 'max:2048'],
            'is_active'                 => ['nullable', 'boolean'],
            'variants'                  => ['required', 'array', 'min:1'],
            'variants.*.id'             => ['nullable', 'exists:product_variants,id'],
            'variants.*.sku'            => ['required', 'string', 'max:50', 'distinct'],
            'variants.*.color'          => ['required', 'string', 'max:50'],
            'variants.*.size'           => ['required', 'string', 'max:20'],
            'variants.*.price'          => ['nullable', 'numeric', 'min:0'],
            'variants.*.stock_quantity' => ['required', 'integer', 'min:0'],
            'variants.*.image'          => ['nullable', 'image', 'max:2048'],
        ]);

        if ($this->hasDuplicateColorSize($data['variants'])) {
            return back()->withInput()->withErrors([
                'variants' => 'Các biến thể của cùng một sản phẩm không được trùng lặp tổ hợp Màu sắc và Kích cỡ.',
            ]);
        }

        // Kiểm tra trùng SKU với các biến thể khác trong database
        foreach ($data['variants'] as $variantData) {
            $skuExists = ProductVariant::where('sku', trim($variantData['sku']))
                ->when(!empty($variantData['id']), fn ($q) => $q->where('id', '!=', $variantData['id']))
                ->exists();

            if ($skuExists) {
                return back()->withInput()->withErrors([
                    'variants' => "Mã SKU \"{$variantData['sku']}\" đã tồn tại trên hệ thống.",
                ]);
            }
        }

        DB::transaction(function () use ($request, $data, $product) {
            $thumbnailPath = $product->thumbnail;
            if ($request->hasFile('thumbnail')) {
                if ($thumbnailPath) {
                    Storage::disk('public')->delete($thumbnailPath);
                }
                $thumbnailPath = $request->file('thumbnail')->store('products', 'public');
            }

            $product->update([
                'category_id' => $data['category_id'],
                'name'        => $data['name'],
                'slug'        => $product->name !== $data['name']
                    ? $this->generateUniqueSlug($data['name'], $product->id)
                    : $product->slug,
                'description' => $data['description'] ?? null,
                'material'    => $data['material'] ?? null,
                'gender'      => $data['gender'],
                'base_price'  => $data['base_price'],
                'tags'        => $this->parseTags($data['tags'] ?? ''),
                'thumbnail'   => $thumbnailPath,
                'is_active'   => $request->boolean('is_active'),
            ]);

            foreach ($data['variants'] as $index => $variantData) {
                $variant = !empty($variantData['id'])
                    ? $product->variants()->find($variantData['id'])
                    : null;

                $variantImagePath = $variant?->image;
                if ($request->hasFile("variants.{$index}.image")) {
                    if ($variantImagePath) {
                        Storage::disk('public')->delete($variantImagePath);
                    }
                    $variantImagePath = $request->file("variants.{$index}.image")->store('variants', 'public');
                }

                $payload = [
                    'sku'            => trim($variantData['sku']),
                    'color'          => $variantData['color'],
                    'size'           => $variantData['size'],
                    'price'          => $variantData['price'] ?? null,
                    'stock_quantity' => $variantData['stock_quantity'],
                    'image'          => $variantImagePath,
                ];

                if ($variant) {
                    $variant->update($payload);
                } else {
                    $product->variants()->create($payload);
                }
            }
        });

        return redirect()->route('admin.products.index')->with('success', 'Cập nhật sản phẩm thành công.');
    }

    public function destroy(Product $product)
    {
        if ($product->thumbnail) {
            Storage::disk('public')->delete($product->thumbnail);
        }

        foreach ($product->variants as $variant) {
            if ($variant->image) {
                Storage::disk('public')->delete($variant->image);
            }
        }

        $product->delete();

        return redirect()->route('admin.products.index')->with('success', 'Đã xóa sản phẩm.');
    }

    public function destroyVariant(ProductVariant $variant)
    {
        if ($variant->product->variants()->count() <= 1) {
            return back()->with('error', 'Sản phẩm phải có ít nhất một biến thể.');
        }

        if ($variant->orderItems()->exists()) {
            return back()->with('error', 'Không thể xóa biến thể đã phát sinh đơn hàng.');
        }

        if ($variant->image) {
            Storage::disk('public')->delete($variant->image);
        }

        $variant->delete();

        return back()->with('success', 'Đã xóa biến thể sản phẩm.');
    }

    private function parseTags(string $tagsString): array
    {
        if (trim($tagsString) === '') {
            return [];
        }

        return collect(explode(',', $tagsString))
            ->map(fn ($tag) => mb_strtolower(trim($tag), 'UTF-8'))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function hasDuplicateColorSize(array $variants): bool
    {
        $seen = [];
        foreach ($variants as $v) {
            $key = mb_strtolower(trim($v['color']), 'UTF-8') . '|' . mb_strtoupper(trim($v['size']), 'UTF-8');
            if (isset($seen[$key])) {
                return true;
            }
            $seen[$key] = true;
        }
        return false;
    }

    private function generateUniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $baseSlug = Str::slug($name);
        $slug = $baseSlug;
        $counter = 1;

        while (
            Product::where('slug', $slug)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = "{$baseSlug}-{$counter}";
            $counter++;
        }

        return $slug;
    }
}