<?php

namespace App\Services\Ai\Actions;

use App\Http\Requests\Admin\StoreGoodsReceiptRequest;
use App\Models\Brand;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\ProductVariant;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Ai\Actions\Concerns\DescribesAmounts;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * "Soạn phiếu nhập cho hàng sắp hết" → one DRAFT goods receipt per supplier
 * for the variants at or below their low-stock threshold. Each variant goes
 * to the supplier (and cost) of its latest confirmed receipt, or to the
 * supplier the staff member named. Drafts change no stock; confirming them
 * stays a separate step on the receipt page.
 */
class DraftGoodsReceiptsAction implements AgentAction
{
    use DescribesAmounts;

    private const MAX_VARIANTS = 200;

    private const NOTE = 'Soạn tự động từ hàng sắp hết qua trợ lý AI.';

    public function __construct(private readonly FormRequestRunner $forms) {}

    public function type(): string
    {
        return 'draft_goods_receipts';
    }

    public function permission(): string
    {
        return 'inventory.manage';
    }

    public function promptSpec(): string
    {
        return <<<'SPEC'
            - "draft_goods_receipts": soạn phiếu nhập NHÁP cho các biến thể tồn kho bằng hoặc dưới ngưỡng cảnh báo, ví dụ
              "soạn phiếu nhập cho hàng sắp hết", "nhập thêm hàng Coolmate sắp hết, mỗi mẫu 30 cái". Mỗi nhà cung cấp
              một phiếu; phiếu nháp chưa làm tăng tồn kho. Dạng:
              {"type": "draft_goods_receipts",
               "supplier": "tên nhà cung cấp nếu nhân viên chỉ định, hoặc \"\" (dùng nhà cung cấp của lần nhập gần nhất)",
               "brand": "nguyên văn tên thương hiệu trong danh sách ở trên hoặc \"\"",
               "product_query": "một phần tên sản phẩm hoặc \"\"",
               "quantity": số lượng nhập mỗi biến thể nếu nhân viên nói, hoặc null (tự tính để tồn kho lên gấp đôi ngưỡng)}
            SPEC;
    }

    public function preview(array $params, User $actor): ActionPreview
    {
        $supplier = $this->namedSupplier($params);
        $variants = $this->lowStockVariants($params);

        if ($variants->isEmpty()) {
            throw new InvalidActionException('Không có biến thể nào đang ở mức sắp hết hàng khớp yêu cầu.');
        }
        if ($variants->count() > self::MAX_VARIANTS) {
            throw new InvalidActionException('Có '.$variants->count().' biến thể sắp hết, nhiều hơn giới hạn '.self::MAX_VARIANTS.' một lần. Hãy thu hẹp theo thương hiệu hoặc sản phẩm.');
        }

        $quantity = $this->number($params['quantity'] ?? null);
        $lastReceipts = $this->lastReceiptItems($variants->modelKeys());
        $receipts = [];
        $noSupplier = [];
        $noCost = 0;

        foreach ($variants as $variant) {
            $last = $lastReceipts->get($variant->id);
            $supplierId = $supplier?->id ?? $last?->supplier_id;
            if ($supplierId === null) {
                $noSupplier[] = $variant->sku;

                continue;
            }

            $noCost += $last ? 0 : 1;
            $receipts[$supplierId][] = [
                'product_variant_id' => $variant->id,
                'quantity' => $quantity !== null ? max(1, (int) round($quantity)) : $variant->reorderQuantity(),
                'cost_price' => $last ? (float) $last->cost_price : 0,
            ];
        }

        if ($receipts === []) {
            throw new InvalidActionException('Các biến thể sắp hết chưa từng được nhập nên chưa biết nhà cung cấp. Hãy nói rõ nhập từ nhà cung cấp nào.');
        }

        $suppliers = Supplier::query()->whereKey(array_keys($receipts))->pluck('name', 'id');
        $skus = $variants->pluck('sku', 'id');
        foreach ($receipts as $supplierId => $items) {
            $this->forms->validate(StoreGoodsReceiptRequest::class, ['supplier_id' => $supplierId, 'notes' => self::NOTE, 'items' => $items], $actor);
        }

        $lines = collect($receipts)->map(function (array $items, int $supplierId) use ($suppliers, $skus): string {
            $total = array_sum(array_map(fn ($item) => $item['quantity'] * $item['cost_price'], $items));
            $sample = implode(', ', array_map(fn ($item) => $skus[$item['product_variant_id']].' ×'.$item['quantity'], array_slice($items, 0, 4)));

            return ($suppliers[$supplierId] ?? 'Nhà cung cấp #'.$supplierId).': '.count($items).' dòng hàng, khoảng '.$this->money($total).' ('.$sample.(count($items) > 4 ? ', …' : '').')';
        })->values()->all();

        $warnings = [];
        if ($noSupplier !== []) {
            $warnings[] = count($noSupplier).' biến thể chưa từng được nhập nên bị bỏ qua: '.implode(', ', array_slice($noSupplier, 0, 6)).(count($noSupplier) > 6 ? ', …' : '').'.';
        }
        if ($noCost > 0) {
            $warnings[] = "{$noCost} dòng chưa có giá nhập trước đó, để 0 ₫; hãy điền giá trước khi xác nhận phiếu.";
        }

        return new ActionPreview(
            title: 'Soạn '.count($receipts).' phiếu nhập nháp cho '.array_sum(array_map('count', $receipts)).' biến thể sắp hết',
            lines: $lines,
            confirmLabel: 'Tạo '.count($receipts).' phiếu nhập nháp',
            params: ['receipts' => $receipts],
            warnings: $warnings,
        );
    }

    public function execute(array $params, User $actor): ActionResult
    {
        $receipts = [];
        foreach ($params['receipts'] ?? [] as $supplierId => $items) {
            $receipts[] = $this->forms->validate(StoreGoodsReceiptRequest::class, ['supplier_id' => $supplierId, 'notes' => self::NOTE, 'items' => $items], $actor)->validated();
        }

        $created = DB::transaction(fn () => array_map(function (array $data) use ($actor): GoodsReceipt {
            $receipt = GoodsReceipt::query()->create([
                'supplier_id' => $data['supplier_id'],
                'status' => 'draft',
                'total_cost' => round(array_sum(array_map(fn ($item) => $item['quantity'] * $item['cost_price'], $data['items'])), 2),
                'notes' => $data['notes'],
                'created_by' => $actor->id,
            ]);
            foreach ($data['items'] as $item) {
                GoodsReceiptItem::query()->create([
                    'goods_receipt_id' => $receipt->id,
                    'product_variant_id' => (int) $item['product_variant_id'],
                    'quantity' => (int) $item['quantity'],
                    'cost_price' => (float) $item['cost_price'],
                    'subtotal' => round($item['quantity'] * $item['cost_price'], 2),
                ]);
            }

            return $receipt->refresh();
        }, $receipts));

        return new ActionResult(
            message: 'Đã tạo '.count($created).' phiếu nhập nháp. Kiểm tra số lượng, giá rồi xác nhận từng phiếu để cộng tồn kho.',
            links: array_map(fn (GoodsReceipt $receipt) => [
                'label' => 'Phiếu '.$receipt->receipt_number,
                'url' => route('admin.goods-receipts.show', $receipt),
            ], $created),
        );
    }

    /**
     * @param  array<string, mixed>  $params
     *
     * @throws InvalidActionException
     */
    private function namedSupplier(array $params): ?Supplier
    {
        $name = is_string($params['supplier'] ?? null) ? trim($params['supplier']) : '';
        if ($name === '') {
            return null;
        }

        return Supplier::query()->where('is_active', true)->where('name', 'like', '%'.addcslashes($name, '%_\\').'%')->orderBy('name')->first()
            ?? throw new InvalidActionException("Không có nhà cung cấp đang hoạt động nào tên \"{$name}\".");
    }

    /**
     * Active variants of active products at or below their threshold (the
     * dashboard's "Sắp hết hàng"), lowest stock first.
     *
     * @param  array<string, mixed>  $params
     * @return \Illuminate\Database\Eloquent\Collection<int, ProductVariant>
     */
    private function lowStockVariants(array $params): \Illuminate\Database\Eloquent\Collection
    {
        $brandName = is_string($params['brand'] ?? null) ? trim($params['brand']) : '';
        $query = is_string($params['product_query'] ?? null) ? trim($params['product_query']) : '';
        $brand = $brandName === '' ? null : ($this->named(Brand::class, $brandName)->first()
            ?? throw new InvalidActionException("Không có thương hiệu \"{$brandName}\"."));

        return ProductVariant::query()
            ->where('is_active', true)
            ->whereColumn('stock_quantity', '<=', 'low_stock_threshold')
            ->whereHas('product', fn (Builder $product) => $product->where('status', 'active')
                ->when($brand, fn (Builder $q) => $q->where('brand_id', $brand->id))
                ->when($query !== '', fn (Builder $q) => $q->where('name', 'like', '%'.addcslashes($query, '%_\\').'%')))
            ->orderBy('stock_quantity')
            ->orderBy('id')
            ->get();
    }

    /**
     * The latest confirmed receipt line of each variant, with its supplier.
     *
     * @param  list<int>  $variantIds
     * @return Collection<int, object{supplier_id: int, cost_price: string}>
     */
    private function lastReceiptItems(array $variantIds): Collection
    {
        return GoodsReceiptItem::query()
            ->join('goods_receipts', 'goods_receipts.id', '=', 'goods_receipt_items.goods_receipt_id')
            ->where('goods_receipts.status', 'confirmed')
            ->whereIn('goods_receipt_items.product_variant_id', $variantIds)
            ->orderByDesc('goods_receipts.confirmed_at')
            ->orderByDesc('goods_receipt_items.id')
            ->get(['goods_receipt_items.product_variant_id', 'goods_receipts.supplier_id', 'goods_receipt_items.cost_price'])
            ->unique('product_variant_id')
            ->keyBy('product_variant_id');
    }
}
