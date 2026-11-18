<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::with('parent')
            ->withCount('products')
            ->latest()
            ->paginate(15);

        return view('admin.categories.index', compact('categories'));
    }

    public function create()
    {
        $parentCategories = Category::whereNull('parent_id')->where('is_active', true)->get();

        return view('admin.categories.create', compact('parentCategories'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'      => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'exists:categories,id'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        Category::create([
            'name'      => $data['name'],
            'slug'      => $this->generateUniqueSlug($data['name']),
            'parent_id' => $data['parent_id'] ?? null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('admin.categories.index')->with('success', 'Thêm danh mục thành công.');
    }

    public function edit(Category $category)
    {
        $parentCategories = Category::whereNull('parent_id')
            ->where('id', '!=', $category->id)
            ->get();

        return view('admin.categories.edit', compact('category', 'parentCategories'));
    }

    public function update(Request $request, Category $category)
    {
        $data = $request->validate([
            'name'      => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'exists:categories,id'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if (($data['parent_id'] ?? null) == $category->id) {
            return back()->withErrors(['parent_id' => 'Danh mục không thể tự làm danh mục cha của chính nó.']);
        }

        $category->update([
            'name'      => $data['name'],
            'slug'      => $category->name !== $data['name']
                ? $this->generateUniqueSlug($data['name'], $category->id)
                : $category->slug,
            'parent_id' => $data['parent_id'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()->route('admin.categories.index')->with('success', 'Cập nhật danh mục thành công.');
    }

    public function destroy(Category $category)
    {
        if ($category->products()->exists()) {
            return back()->with('error', 'Không thể xóa danh mục đang chứa sản phẩm.');
        }

        $category->delete();

        return redirect()->route('admin.categories.index')->with('success', 'Đã xóa danh mục.');
    }

    private function generateUniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $baseSlug = Str::slug($name);
        $slug = $baseSlug;
        $counter = 1;

        while (
            Category::where('slug', $slug)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = "{$baseSlug}-{$counter}";
            $counter++;
        }

        return $slug;
    }
}