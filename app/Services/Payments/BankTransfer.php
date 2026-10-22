<?php

namespace App\Services\Payments;

use App\Models\Order;
use App\Models\PaymentSetting;

/**
 * The shop's receiving bank account and the VietQR code customers scan.
 * The transfer memo is the bare order number — that is what the SePay
 * webhook looks for to match the money to the order.
 *
 * Each value comes from the settings an admin saved at
 * /admin/cai-dat/thanh-toan (Admin\PaymentSettingController); an empty one
 * falls back to .env (config store.bank_transfer.* / services.sepay.*).
 * Pass a PaymentSetting to resolve unsaved form input instead (the QR
 * preview on that screen).
 */
class BankTransfer
{
    /**
     * The container builds it from the saved row (AppServiceProvider).
     */
    public function __construct(private readonly ?PaymentSetting $setting = null) {}

    /**
     * Offered at checkout only once the account is configured.
     */
    public function isEnabled(): bool
    {
        return $this->bankId() !== '' && $this->accountNumber() !== '';
    }

    public function bankId(): string
    {
        return $this->setting?->bank_id ?: (string) config('store.bank_transfer.bank_id');
    }

    public function accountNumber(): string
    {
        return $this->setting?->account_number ?: (string) config('store.bank_transfer.account_number');
    }

    public function accountName(): string
    {
        return $this->setting?->account_name ?: (string) config('store.bank_transfer.account_name');
    }

    /**
     * The key SePay must send as "Authorization: Apikey <key>"; "" refuses every call.
     */
    public function webhookApiKey(): string
    {
        return $this->setting?->sepay_webhook_api_key ?: (string) config('services.sepay.webhook_api_key');
    }

    /**
     * The URL to paste into SePay: the saved public one, else this app's own
     * route (APP_URL), which is right once deployed on a real domain.
     */
    public function webhookUrl(): string
    {
        return $this->setting?->webhook_url ?: route('webhooks.sepay');
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
        return $this->quickLink((int) round((float) $order->grand_total), $this->transferMemo($order));
    }

    public function quickLink(int $amount, string $memo): string
    {
        return sprintf(
            'https://img.vietqr.io/image/%s-%s-compact2.png?%s',
            rawurlencode($this->bankId()),
            rawurlencode($this->accountNumber()),
            http_build_query([
                'amount' => $amount,
                'addInfo' => $memo,
                'accountName' => $this->accountName(),
            ], '', '&', PHP_QUERY_RFC3986),
        );
    }
}
