<?php

namespace App\Actions;

use App\Models\AuditLog;
use App\Models\GoodsReceipt;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Confirm a goods receipt:
 *  - Lock the receipt row (prevents concurrent double-confirm).
 *  - Lock each affected variant row (prevents concurrent stock over/under-count).
 *  - Increment each variant's stock_quantity by the receipt item's quantity.
 *  - Update the receipt status to "confirmed" with timestamp and actor.
 *  - Write an audit log entry.
 *
 * The action is idempotent: calling it on an already-confirmed receipt is a no-op.
 */
class ConfirmGoodsReceipt
{
    /**
     * @return array{receipt: GoodsReceipt, error: ?string}
     */
    public function execute(GoodsReceipt $receipt, User $actor): array
    {
        $result = DB::transaction(function () use ($receipt, $actor) {
            // Re-fetch with a row lock to prevent concurrent confirms.
            $lockedReceipt = GoodsReceipt::query()
                ->whereKey($receipt->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedReceipt->status === 'confirmed') {
                return ['receipt' => $lockedReceipt, 'error' => 'Phiếu nhập đã được xác nhận trước đó.'];
            }

            if ($lockedReceipt->status !== 'draft') {
                return ['receipt' => $lockedReceipt, 'error' => "Chỉ phiếu nhập ở trạng thái 'draft' mới có thể xác nhận (trạng thái hiện tại: {$lockedReceipt->status})."];
            }

            $items = $lockedReceipt->items()->get();

            if ($items->isEmpty()) {
                return ['receipt' => $lockedReceipt, 'error' => 'Không có dòng hàng nào để xác nhận.'];
            }

            // Group quantities by variant so we only touch each variant once.
            $quantitiesByVariant = $items
                ->groupBy('product_variant_id')
                ->map(fn ($group) => (int) $group->sum('quantity'));

            foreach ($quantitiesByVariant as $variantId => $quantity) {
                if ($quantity <= 0) {
                    continue;
                }

                $variant = ProductVariant::query()
                    ->whereKey($variantId)
                    ->lockForUpdate()
                    ->first();

                if (! $variant) {
                    return ['receipt' => $lockedReceipt, 'error' => "Biến thể #{$variantId} không tồn tại."];
                }

                $variant->stock_quantity = (int) $variant->stock_quantity + $quantity;
                $variant->save();
            }

            $lockedReceipt->status = 'confirmed';
            $lockedReceipt->confirmed_by = $actor->id;
            $lockedReceipt->confirmed_at = now();
            $lockedReceipt->save();

            AuditLog::query()->create([
                'actor_id' => $actor->id,
                'action' => 'goods_receipt.confirmed',
                'subject_type' => $lockedReceipt->getMorphClass(),
                'subject_id' => $lockedReceipt->id,
                'old_values' => ['status' => 'draft'],
                'new_values' => [
                    'status' => 'confirmed',
                    'confirmed_by' => $actor->id,
                    'confirmed_at' => $lockedReceipt->confirmed_at->toIso8601String(),
                    'items' => $items->map(fn ($item) => [
                        'variant_id' => $item->product_variant_id,
                        'quantity' => $item->quantity,
                        'cost_price' => (float) $item->cost_price,
                    ])->all(),
                ],
            ]);

            return ['receipt' => $lockedReceipt, 'error' => null];
        });

        return $result;
    }
}
