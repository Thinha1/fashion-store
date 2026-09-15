<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCategoryRequest;
use App\Models\Category;
use App\Support\AdminPagination;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(Request $request): View
    {
        $categories = Category::query()->with('parent')->withCount('products', 'children')
            ->orderBy('sort_order')->orderBy('name')->orderBy('id')
            ->paginate(AdminPagination::perPage($request))->withQueryString();

        return view('admin.categories.index', ['categories' => $categories]);
    }

    public function create(?int $parentId = null): View
    {
        $category = new Category(['is_active' => true, 'sort_order' => 0, 'parent_id' => $parentId]);
        $parents = Category::query()->whereNull('parent_id')->orderBy('name')->get();

        return view('admin.categories.create', [
            'category' => $category,
            'parents' => $parents,
            'title' => $parentId ? 'Thêm danh mục con' : 'Thêm danh mục',
        ]);
    }

    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        $category = Category::query()->create($request->validated() + ['is_active' => $request->boolean('is_active', true)]);

        return redirect()->route('admin.categories.index')
            ->with('status', "Danh mục \"{$category->name}\" đã được tạo.");
    }

    public function edit(Category $category): View
    {
        $parents = Category::query()->whereNull('parent_id')->orderBy('name')->get();

        return view('admin.categories.edit', [
            'category' => $category,
            'parents' => $parents,
        ]);
    }

    public function update(StoreCategoryRequest $request, Category $category): RedirectResponse
    {
        $category->update($request->validated() + ['is_active' => $request->boolean('is_active', true)]);

        return redirect()->route('admin.categories.index')
            ->with('status', "Danh mục \"{$category->name}\" đã được cập nhật.");
    }

    public function destroy(Category $category): RedirectResponse
    {
        abort_unless(auth()->user()->can('products.manage'), 403);

        if ($category->products()->exists()) {
            return back()->with('error', 'Không thể xóa danh mục đang có sản phẩm.');
        }

        if ($category->children()->exists()) {
            return back()->with('error', 'Không thể xóa danh mục đang có danh mục con. Hãy xóa hoặc chuyển danh mục con trước.');
        }

        $category->delete();

        return redirect()->route('admin.categories.index')->with('status', 'Danh mục đã được xóa.');
    }
}
