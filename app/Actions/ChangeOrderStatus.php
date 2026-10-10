<?php

namespace App\Actions;

use App\Models\AuditLog;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Staff moving an order one step along BUSINESS_FLOWS.md §2:
 *  - lock the order row and re-check the move against its CURRENT status, so
 *    two staff clicking at once can't skip or repeat a step;
 *  - cancelling hands off to CancelOrder (restock + coupon use back);
 *  - a COD order reaching `delivered` is marked paid — the cash was collected
 *    on delivery, and the dashboard only counts delivered + paid revenue;
 *  - every change appends to `status_history` and writes an audit log.
 */
class ChangeOrderStatus
{
    public function __construct(private CancelOrder $cancelOrder) {}

    /**
     * @throws OrderStatusException
     */
    public function execute(Order $order, string $toStatus, User $actor, ?string $note = null): Order
    {
        if ($toStatus === 'cancelled') {
            return $this->cancelOrder->execute(
                $order,
                $actor,
                $note ?: 'Cửa hàng hủy đơn',
                Order::staffCancellableStatuses(),
            );
        }

        return DB::transaction(function () use ($order, $toStatus, $actor, $note): Order {
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $fromStatus = $lockedOrder->status;

            if (! in_array($toStatus, $lockedOrder->nextStaffStatuses(), true)) {
                $target = Order::STATUS_LABELS[$toStatus] ?? $toStatus;

                throw new OrderStatusException("Không thể chuyển đơn từ \"{$lockedOrder->statusLabel()}\" sang \"{$target}\".");
            }

            $oldValues = ['status' => $fromStatus];
            $newValues = ['status' => $toStatus, 'note' => $note];

            if ($toStatus === 'delivered' && $lockedOrder->payment_method === 'cod' && $lockedOrder->payment_status === 'unpaid') {
                $oldValues['payment_status'] = 'unpaid';
                $newValues['payment_status'] = 'paid';
                $lockedOrder->payment_status = 'paid';
            }

            $lockedOrder->status = $toStatus;
            $lockedOrder->status_history = [...($lockedOrder->status_history ?? []), [
                'from' => $fromStatus,
                'to' => $toStatus,
                'actor_id' => $actor->id,
                'note' => $note,
                'at' => now()->toIso8601String(),
            ]];
            $lockedOrder->updated_by = $actor->id;
            $lockedOrder->save();

            AuditLog::query()->create([
                'actor_id' => $actor->id,
                'action' => 'order.status_changed',
                'subject_type' => $lockedOrder->getMorphClass(),
                'subject_id' => $lockedOrder->id,
                'old_values' => $oldValues,
                'new_values' => $newValues,
            ]);

            return $lockedOrder;
        });
    }
}
