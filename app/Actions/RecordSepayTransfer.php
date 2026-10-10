<?php

namespace App\Actions;

use App\Models\AuditLog;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

/**
 * Match one incoming SePay transfer to an order (BUSINESS_FLOWS.md §6).
 * The order is marked paid only when BOTH the order number found in the
 * transfer memo AND the amount match a bank-transfer order still waiting for
 * money. Anything else is only written to the audit log for staff to look at
 * — an order is never changed on a guess.
 */
class RecordSepayTransfer
{
    public const MATCHED = 'matched';

    public const DUPLICATE = 'duplicate';

    public const UNMATCHED = 'unmatched';

    public const IGNORED = 'ignored';

    /**
     * Payment statuses that still accept money. A receipt under review or
     * rejected is superseded by the bank actually reporting the transfer.
     */
    private const AWAITING_PAYMENT = ['unpaid', 'pending_review', 'rejected'];

    /**
     * "DH" + yymmdd + 6 letters/digits — see PlaceOrder::newOrderNumber().
     */
    private const ORDER_NUMBER_PATTERN = '/(?<![A-Z0-9])DH\d{6}[A-Z0-9]{6}/';

    /**
     * @param  array{id: int|string, transferType: string, transferAmount: int|float|string, content?: ?string, code?: ?string, referenceCode?: ?string, gateway?: ?string, transactionDate?: ?string}  $payload
     * @return string one of the MATCHED / DUPLICATE / UNMATCHED / IGNORED constants
     */
    public function execute(array $payload): string
    {
        if ($payload['transferType'] !== 'in') {
            return self::IGNORED;
        }

        $transactionCode = 'SEPAY-'.$payload['id'];
        $amount = (int) round((float) $payload['transferAmount']);
        $orderNumber = $this->findOrderNumber($payload);

        return DB::transaction(function () use ($payload, $transactionCode, $amount, $orderNumber): string {
            $order = $orderNumber === null ? null : Order::query()
                ->where('order_number', $orderNumber)
                ->lockForUpdate()
                ->first();

            $problem = match (true) {
                $order === null => 'order_not_found',
                $order->payment_status === 'paid' && $order->transaction_code === $transactionCode => null,
                ! in_array($order->payment_status, self::AWAITING_PAYMENT, true) => 'order_already_'.$order->payment_status,
                $order->payment_method !== 'bank_transfer' => 'order_not_bank_transfer',
                $order->status === 'cancelled' => 'order_cancelled',
                $amount !== (int) round((float) $order->grand_total) => 'amount_mismatch',
                default => null,
            };

            // SePay retries the same transfer until it gets a 200 — already recorded.
            if ($problem === null && $order->payment_status === 'paid') {
                return self::DUPLICATE;
            }

            if ($problem !== null) {
                $this->log('payment.sepay_unmatched', $order, [
                    'reason' => $problem,
                    'order_number' => $orderNumber,
                    'amount' => $amount,
                    'payload' => $payload,
                ]);

                return self::UNMATCHED;
            }

            $previousPaymentStatus = $order->payment_status;
            $order->forceFill([
                'payment_status' => 'paid',
                'transaction_code' => $transactionCode,
                'payment_reviewed_by' => null,
                'payment_reviewed_at' => now(),
                'payment_rejection_reason' => null,
            ])->save();

            $this->log('payment.sepay_matched', $order, [
                'payment_status' => 'paid',
                'transaction_code' => $transactionCode,
                'amount' => $amount,
                'reference_code' => $payload['referenceCode'] ?? null,
                'gateway' => $payload['gateway'] ?? null,
            ], ['payment_status' => $previousPaymentStatus]);

            return self::MATCHED;
        });
    }

    /**
     * SePay's own `code` (when payment-code detection is set up there) wins;
     * otherwise search the memo, which banks may upper-case or pad.
     *
     * @param  array<string, mixed>  $payload
     */
    private function findOrderNumber(array $payload): ?string
    {
        foreach ([$payload['code'] ?? null, $payload['content'] ?? null] as $text) {
            if (is_string($text) && preg_match(self::ORDER_NUMBER_PATTERN, mb_strtoupper($text), $match)) {
                return $match[0];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $newValues
     * @param  array<string, mixed>|null  $oldValues
     */
    private function log(string $action, ?Order $order, array $newValues, ?array $oldValues = null): void
    {
        AuditLog::query()->create([
            'actor_id' => null,
            'action' => $action,
            'subject_type' => (new Order)->getMorphClass(),
            'subject_id' => $order?->id,
            'old_values' => $oldValues,
            'new_values' => $newValues,
        ]);
    }
}
