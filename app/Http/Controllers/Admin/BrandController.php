<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreBrandRequest;
use App\Models\Brand;
use App\Support\AdminPagination;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class BrandController extends Controller
{
    public function index(Request $request): View
    {
        $brands = Brand::query()->withCount('products')->orderBy('name')->orderBy('id')
            ->paginate(AdminPagination::perPage($request))->withQueryString();

        return view('admin.brands.index', ['brands' => $brands]);
    }

    public function create(): View
    {
        return view('admin.brands.create', ['brand' => new Brand(['is_active' => true])]);
    }

    public function store(StoreBrandRequest $request): RedirectResponse
    {
        $data = $request->safe()->except('logo');
        $data['logo_path'] = $this->storeLogo($request);

        $brand = Brand::query()->create($data + ['is_active' => $request->boolean('is_active', true)]);

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
        $data = $request->safe()->except('logo');

        if ($newLogoPath = $this->storeLogo($request)) {
            if ($brand->logo_path) {
                Storage::disk('s3')->delete($brand->logo_path);
            }

            $data['logo_path'] = $newLogoPath;
        }

        $brand->update($data + ['is_active' => $request->boolean('is_active', true)]);

        return redirect()->route('admin.brands.show', $brand)
            ->with('status', "Thương hiệu \"{$brand->name}\" đã được cập nhật.");
    }

    public function destroy(Brand $brand): RedirectResponse
    {
        abort_unless(auth()->user()->can('products.manage'), 403);

        if ($brand->products()->exists()) {
            return back()->with('error', 'Không thể xóa thương hiệu đang có sản phẩm. Hãy chuyển các sản phẩm sang thương hiệu khác trước.');
        }

        if ($brand->logo_path) {
            Storage::disk('s3')->delete($brand->logo_path);
        }

        $brand->delete();

        return redirect()->route('admin.brands.index')->with('status', 'Thương hiệu đã được xóa.');
    }

    /**
     * Upload the request's `logo` file (if present) to the `s3` disk
     * (MinIO in dev) and return its stored path, or null if none was sent.
     */
    private function storeLogo(StoreBrandRequest $request): ?string
    {
        $logo = $request->file('logo');

        if (! $logo || ! $logo->isValid()) {
            return null;
        }

        return $logo->store('brands', 's3');
    }
}
