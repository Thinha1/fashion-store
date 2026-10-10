<?php

namespace App\Services\Payments;

use App\Models\Order;
use Carbon\CarbonInterface;

/**
 * The shop's receiving bank account and the VietQR code customers scan.
 * The transfer memo is the bare order number — that is what the SePay
 * webhook looks for to match the money to the order.
 */
class BankTransfer
{
    /**
     * Offered at checkout only once the account is configured.
     */
    public function isEnabled(): bool
    {
        return filled(config('store.bank_transfer.bank_id'))
            && filled(config('store.bank_transfer.account_number'));
    }

    public function bankId(): string
    {
        return (string) config('store.bank_transfer.bank_id');
    }

    public function accountNumber(): string
    {
        return (string) config('store.bank_transfer.account_number');
    }

    public function accountName(): string
    {
        return (string) config('store.bank_transfer.account_name');
    }

    public function transferMemo(Order $order): string
    {
        return $order->order_number;
    }

    /**
     * VietQR "quick link" image with the amount and memo pre-filled, so the
     * customer's banking app fills everything in when they scan it.
     */
    public function qrImageUrl(Order $order): string
    {
        return sprintf(
            'https://img.vietqr.io/image/%s-%s-compact2.png?%s',
            rawurlencode($this->bankId()),
            rawurlencode($this->accountNumber()),
            http_build_query([
                'amount' => (int) round((float) $order->grand_total),
                'addInfo' => $this->transferMemo($order),
                'accountName' => $this->accountName(),
            ], '', '&', PHP_QUERY_RFC3986),
        );
    }

    /**
     * The customer may send a receipt instead of waiting for the webhook once
     * the wait is over and the money still hasn't been matched — or right
     * away after a previous receipt was rejected.
     */
    public function canSubmitProof(Order $order): bool
    {
        if ($order->payment_method !== 'bank_transfer' || $order->status === 'cancelled') {
            return false;
        }

        return $order->payment_status === 'rejected'
            || ($order->payment_status === 'unpaid' && $this->proofAllowedAt($order)->isPast());
    }

    public function proofAllowedAt(Order $order): CarbonInterface
    {
        return $order->placed_at->copy()->addMinutes((int) config('store.bank_transfer.proof_wait_minutes'));
    }

    public function proofDisk(): string
    {
        return (string) config('store.bank_transfer.proof_disk');
    }
}
