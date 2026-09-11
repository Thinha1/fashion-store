<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSupplierRequest;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SupplierController extends Controller
{
    public function index(): View
    {
        $suppliers = Supplier::query()->withCount('goodsReceipts')->orderBy('name')->paginate(20);

        return view('admin.suppliers.index', ['suppliers' => $suppliers]);
    }

    public function create(): View
    {
        return view('admin.suppliers.create', ['supplier' => new Supplier(['is_active' => true])]);
    }

    public function store(StoreSupplierRequest $request): RedirectResponse
    {
        $supplier = Supplier::query()->create($request->validated() + ['is_active' => $request->boolean('is_active', true)]);

        return redirect()->route('admin.suppliers.index')
            ->with('status', "Nhà cung cấp \"{$supplier->name}\" đã được tạo.");
    }

    public function edit(Supplier $supplier): View
    {
        return view('admin.suppliers.edit', ['supplier' => $supplier]);
    }

    public function update(StoreSupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $supplier->update($request->validated() + ['is_active' => $request->boolean('is_active', true)]);

        return redirect()->route('admin.suppliers.index')
            ->with('status', "Nhà cung cấp \"{$supplier->name}\" đã được cập nhật.");
    }

    public function destroy(Supplier $supplier): RedirectResponse
    {
        abort_unless(auth()->user()->can('suppliers.manage'), 403);

        if ($supplier->goodsReceipts()->exists()) {
            return back()->with('error', 'Không thể xóa nhà cung cấp đang có phiếu nhập.');
        }

        $supplier->delete();

        return redirect()->route('admin.suppliers.index')->with('status', 'Nhà cung cấp đã được xóa.');
    }
}
