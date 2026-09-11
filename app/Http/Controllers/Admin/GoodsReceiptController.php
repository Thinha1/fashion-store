<?php

namespace App\Http\Controllers\Admin;

use App\Actions\ConfirmGoodsReceipt;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreGoodsReceiptRequest;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class GoodsReceiptController extends Controller
{
    public function index(): View
    {
        $receipts = GoodsReceipt::query()
            ->with('supplier:id,name')
            ->withCount('items')
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('admin.goods-receipts.index', ['receipts' => $receipts]);
    }

    public function create(): View
    {
        return view('admin.goods-receipts.create', [
            'receipt' => new GoodsReceipt(['status' => 'draft', 'total_cost' => 0]),
            'suppliers' => Supplier::query()->where('is_active', true)->orderBy('name')->get(),
            'items' => collect(),
        ]);
    }

    public function store(StoreGoodsReceiptRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $items = $data['items'] ?? [];

        $totalCost = $this->calculateTotalCost($items);

        $actorId = auth()->id();

        $receipt = DB::transaction(function () use ($data, $items, $totalCost, $actorId) {
            $receipt = GoodsReceipt::query()->create([
                'receipt_number' => $this->generateReceiptNumber(),
                'supplier_id' => $data['supplier_id'],
                'status' => 'draft',
                'total_cost' => $totalCost,
                'notes' => $data['notes'] ?? null,
                'created_by' => $actorId,
            ]);

            $this->storeItems($receipt, $items);

            return $receipt;
        });

        return redirect()->route('admin.goods-receipts.show', $receipt)
            ->with('status', "Phiếu nhập {$receipt->receipt_number} đã được tạo (bản nháp).");
    }

    public function show(GoodsReceipt $goodsReceipt): View
    {
        $goodsReceipt->load(['supplier:id,name', 'items.productVariant', 'confirmedBy:id,name']);

        return view('admin.goods-receipts.show', ['receipt' => $goodsReceipt]);
    }

    public function edit(GoodsReceipt $goodsReceipt): View
    {
        abort_unless($goodsReceipt->status === 'draft', 403, 'Chỉ phiếu nhập ở trạng thái draft mới có thể sửa.');

        $goodsReceipt->load('items.productVariant');

        return view('admin.goods-receipts.edit', [
            'receipt' => $goodsReceipt,
            'suppliers' => Supplier::query()->where('is_active', true)->orderBy('name')->get(),
            'items' => $goodsReceipt->items,
        ]);
    }

    public function update(StoreGoodsReceiptRequest $request, GoodsReceipt $goodsReceipt): RedirectResponse
    {
        abort_unless($goodsReceipt->status === 'draft', 403, 'Chỉ phiếu nhập ở trạng thái draft mới có thể sửa.');

        $data = $request->validated();
        $items = $data['items'] ?? [];
        $totalCost = $this->calculateTotalCost($items);

        DB::transaction(function () use ($goodsReceipt, $data, $items, $totalCost) {
            $goodsReceipt->update([
                'supplier_id' => $data['supplier_id'],
                'notes' => $data['notes'] ?? null,
                'total_cost' => $totalCost,
            ]);

            $goodsReceipt->items()->delete();
            $this->storeItems($goodsReceipt, $items);
        });

        return redirect()->route('admin.goods-receipts.show', $goodsReceipt)
            ->with('status', "Phiếu nhập {$goodsReceipt->receipt_number} đã được cập nhật.");
    }

    public function destroy(GoodsReceipt $goodsReceipt): RedirectResponse
    {
        abort_unless(auth()->user()->can('inventory.manage'), 403);

        abort_unless($goodsReceipt->status === 'draft', 403, 'Chỉ phiếu nhập ở trạng thái draft mới có thể xóa.');

        $goodsReceipt->delete();

        return redirect()->route('admin.goods-receipts.index')->with('status', 'Phiếu nhập đã được xóa.');
    }

    public function confirm(GoodsReceipt $goodsReceipt): RedirectResponse
    {
        $action = new ConfirmGoodsReceipt;
        $result = $action->execute($goodsReceipt, $this->requestUser());

        if ($result['error'] !== null) {
            return back()->with('error', $result['error']);
        }

        return redirect()->route('admin.goods-receipts.show', $result['receipt'])
            ->with('status', 'Phiếu nhập đã được xác nhận. Tồn kho đã được cập nhật.');
    }

    private function requestUser(): User
    {
        return auth()->user();
    }

    private function generateReceiptNumber(): string
    {
        return 'GR-'.now()->format('Ymd').'-'.strtoupper(Str::random(5));
    }

    private function calculateTotalCost(array $items): float
    {
        $total = 0;
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $qty = (int) ($item['quantity'] ?? 0);
            $price = (float) ($item['cost_price'] ?? 0);
            $total += $qty * $price;
        }

        return round($total, 2);
    }

    private function storeItems(GoodsReceipt $receipt, array $items): void
    {
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $qty = (int) $item['quantity'];
            $price = (float) $item['cost_price'];

            GoodsReceiptItem::query()->create([
                'goods_receipt_id' => $receipt->id,
                'product_variant_id' => (int) $item['product_variant_id'],
                'quantity' => $qty,
                'cost_price' => $price,
                'subtotal' => round($qty * $price, 2),
            ]);
        }
    }
}
