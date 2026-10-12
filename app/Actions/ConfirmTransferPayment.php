<?php

namespace App\Actions;

use App\Models\AuditLog;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Staff confirm by hand that a bank transfer reached the shop's account —
 * for the transfers the SePay webhook couldn't match (e.g. the customer
 * changed the transfer memo). No receipt is involved: staff check the bank
 * account themselves. Locks the order and only acts while it is still an
 * unpaid, uncancelled bank-transfer order, so it never overrides a webhook
 * that matched the money in the meantime.
 */
class ConfirmTransferPayment
{
    /**
     * @throws OrderStatusException
     */
    public function execute(Order $order, User $staff): Order
    {
        return DB::transaction(function () use ($order, $staff): Order {
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($lockedOrder->payment_method !== 'bank_transfer') {
                throw new OrderStatusException("Đơn {$lockedOrder->order_number} không thanh toán bằng chuyển khoản.");
            }

            if ($lockedOrder->status === 'cancelled') {
                throw new OrderStatusException("Đơn {$lockedOrder->order_number} đã hủy.");
            }

            if ($lockedOrder->payment_status !== 'unpaid') {
                throw new OrderStatusException("Đơn {$lockedOrder->order_number} không còn chờ thanh toán (hiện: {$lockedOrder->paymentStatusLabel()}).");
            }

            $lockedOrder->forceFill([
                'payment_status' => 'paid',
                'payment_reviewed_by' => $staff->id,
                'payment_reviewed_at' => now(),
                'updated_by' => $staff->id,
            ])->save();

            AuditLog::query()->create([
                'actor_id' => $staff->id,
                'action' => 'payment.confirmed_manually',
                'subject_type' => $lockedOrder->getMorphClass(),
                'subject_id' => $lockedOrder->id,
                'old_values' => ['payment_status' => 'unpaid'],
                'new_values' => ['payment_status' => 'paid'],
            ]);

            return $lockedOrder;
        });
    }
}
