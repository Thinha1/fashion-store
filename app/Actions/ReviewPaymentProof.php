<?php

namespace App\Actions;

use App\Models\AuditLog;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Staff decision on a customer's transfer receipt. Locks the order and only
 * acts while it is still `pending_review` — if the SePay webhook matched the
 * money in the meantime, there is nothing left to review.
 */
class ReviewPaymentProof
{
    /**
     * @throws OrderStatusException
     */
    public function approve(Order $order, User $reviewer): Order
    {
        return $this->review($order, $reviewer, 'paid', null);
    }

    /**
     * @throws OrderStatusException
     */
    public function reject(Order $order, User $reviewer, string $reason): Order
    {
        return $this->review($order, $reviewer, 'rejected', $reason);
    }

    private function review(Order $order, User $reviewer, string $decision, ?string $reason): Order
    {
        return DB::transaction(function () use ($order, $reviewer, $decision, $reason): Order {
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($lockedOrder->payment_status !== 'pending_review') {
                throw new OrderStatusException("Đơn {$lockedOrder->order_number} không còn chờ duyệt (hiện: {$lockedOrder->paymentStatusLabel()}).");
            }

            $lockedOrder->forceFill([
                'payment_status' => $decision,
                'payment_reviewed_by' => $reviewer->id,
                'payment_reviewed_at' => now(),
                'payment_rejection_reason' => $reason,
                'updated_by' => $reviewer->id,
            ])->save();

            AuditLog::query()->create([
                'actor_id' => $reviewer->id,
                'action' => $decision === 'paid' ? 'payment.proof_approved' : 'payment.proof_rejected',
                'subject_type' => $lockedOrder->getMorphClass(),
                'subject_id' => $lockedOrder->id,
                'old_values' => ['payment_status' => 'pending_review'],
                'new_values' => ['payment_status' => $decision, 'reason' => $reason],
            ]);

            return $lockedOrder;
        });
    }
}
