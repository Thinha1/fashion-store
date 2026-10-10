<?php

namespace App\Actions;

use App\Models\AuditLog;
use App\Models\Discount;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Cancel an order and put its stock back (BUSINESS_FLOWS.md §2), in one
 * transaction:
 *  - lock the order row so a cancel can't race an admin status change;
 *  - only the given statuses may be cancelled (customers: `pending` only);
 *  - lock and restock each variant, give the coupon use back;
 *  - append to `status_history` and audit-log stock before/after.
 * Written for the customer now; the admin order screen can reuse it.
 */
class CancelOrder
{
    /**
     * @param  list<string>  $cancellableStatuses
     *
     * @throws OrderNotCancellableException when the order is no longer cancellable (message is customer-facing)
     */
    public function execute(Order $order, User $actor, string $note, array $cancellableStatuses = ['pending']): Order
    {
        return DB::transaction(function () use ($order, $actor, $note, $cancellableStatuses): Order {
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if (! in_array($lockedOrder->status, $cancellableStatuses, true)) {
                throw new OrderNotCancellableException("Đơn hàng đang ở trạng thái \"{$lockedOrder->statusLabel()}\" nên không thể hủy.");
            }

            $items = $lockedOrder->items()->whereNotNull('product_variant_id')->get();
            $variants = ProductVariant::withTrashed()
                ->whereIn('id', $items->pluck('product_variant_id'))
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $stockChanges = $items->groupBy('product_variant_id')->map(function ($variantItems, int $variantId) use ($variants): ?array {
                $variant = $variants->get($variantId);

                if (! $variant) {
                    return null;
                }

                $quantity = (int) $variantItems->sum(fn (OrderItem $item): int => $item->quantity);
                $before = $variant->stock_quantity;
                $variant->stock_quantity = $before + $quantity;
                $variant->save();

                return ['variant_id' => $variantId, 'quantity' => $quantity, 'stock_before' => $before, 'stock_after' => $variant->stock_quantity];
            })->filter()->values()->all();

            if ($lockedOrder->discount_id) {
                Discount::query()->whereKey($lockedOrder->discount_id)->where('used_count', '>', 0)->lockForUpdate()->first()?->decrement('used_count');
            }

            $previousStatus = $lockedOrder->status;
            $lockedOrder->status = 'cancelled';
            $lockedOrder->status_history = [...($lockedOrder->status_history ?? []), [
                'from' => $previousStatus,
                'to' => 'cancelled',
                'actor_id' => $actor->id,
                'note' => $note,
                'at' => now()->toIso8601String(),
            ]];

            // `orders.updated_by` records staff edits, not the customer's own.
            if ($actor->id !== $lockedOrder->user_id) {
                $lockedOrder->updated_by = $actor->id;
            }

            $lockedOrder->save();

            AuditLog::query()->create([
                'actor_id' => $actor->id,
                'action' => 'order.cancelled',
                'subject_type' => $lockedOrder->getMorphClass(),
                'subject_id' => $lockedOrder->id,
                'old_values' => ['status' => $previousStatus],
                'new_values' => ['status' => 'cancelled', 'note' => $note, 'stock' => $stockChanges],
            ]);

            return $lockedOrder;
        });
    }
}
