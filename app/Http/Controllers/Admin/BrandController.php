<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreBrandRequest;
use App\Models\Brand;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class BrandController extends Controller
{
    public function index(): View
    {
        $brands = Brand::query()->withCount('products')->orderBy('name')->paginate(20);

        return view('admin.brands.index', ['brands' => $brands]);
    }

    public function create(): View
    {
        return view('admin.brands.create', ['brand' => new Brand(['is_active' => true])]);
    }

    public function store(StoreBrandRequest $request): RedirectResponse
    {
        $brand = Brand::query()->create($request->validated() + ['is_active' => $request->boolean('is_active', true)]);

        return redirect()->route('admin.brands.show', $brand)
            ->with('status', "Thương hiệu \"{$brand->name}\" đã được tạo.");
    }

    public function show(Brand $brand): View
    {
        $brand->loadCount('products');

        return view('admin.brands.show', ['brand' => $brand]);
    }

    public function edit(Brand $brand): View
    {
        return view('admin.brands.edit', ['brand' => $brand]);
    }

    public function update(StoreBrandRequest $request, Brand $brand): RedirectResponse
    {
        $brand->update($request->validated() + ['is_active' => $request->boolean('is_active', true)]);

        return redirect()->route('admin.brands.show', $brand)
            ->with('status', "Thương hiệu \"{$brand->name}\" đã được cập nhật.");
    }

    public function destroy(Brand $brand): RedirectResponse
    {
        abort_unless(auth()->user()->can('products.manage'), 403);

        if ($brand->products()->exists()) {
            return back()->with('error', 'Không thể xóa thương hiệu đang có sản phẩm. Hãy chuyển các sản phẩm sang thương hiệu khác trước.');
        }

        $brand->delete();

        return redirect()->route('admin.brands.index')->with('status', 'Thương hiệu đã được xóa.');
    }
}
