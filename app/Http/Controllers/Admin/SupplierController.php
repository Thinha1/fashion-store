<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSupplierRequest;
use App\Models\Supplier;
use App\Support\AdminPagination;
use App\Support\AdminSorting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupplierController extends Controller
{
    public function index(Request $request): View
    {
        $sorting = new AdminSorting($request, [
            'name' => 'name', 'phone' => 'phone', 'email' => 'email', 'tax_code' => 'tax_code',
            'goods_receipts_count' => 'goods_receipts_count', 'is_active' => 'is_active',
        ]);
        $suppliers = $sorting->apply(Supplier::query()->withCount('goodsReceipts')->orderBy('name')->orderBy('id'))
            ->paginate(AdminPagination::perPage($request))->withQueryString();

        return view('admin.suppliers.index', ['suppliers' => $suppliers, 'sorting' => $sorting]);
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
