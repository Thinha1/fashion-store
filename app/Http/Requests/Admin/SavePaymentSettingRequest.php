<?php

namespace App\Http\Requests\Admin;

/**
 * The bank-transfer settings form (/admin/cai-dat/thanh-toan).
 */
class SavePaymentSettingRequest extends BaseAdminRequest
{
    public function permissionCode(): string
    {
        return 'settings.payment';
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            // VietQR bank code (VCB, MB…) or 6-digit BIN.
            'bank_id' => ['nullable', 'string', 'max:20', 'regex:/^[A-Za-z0-9]+$/'],
            'account_number' => ['nullable', 'string', 'max:30', 'regex:/^[0-9]+$/'],
            'account_name' => ['nullable', 'string', 'max:100'],
            // Left blank = keep the saved key; it never overwrites one.
            'sepay_webhook_api_key' => ['nullable', 'string', 'min:16', 'max:255', 'regex:/^\S+$/'],
            'clear_sepay_webhook_api_key' => ['boolean'],
            // SePay only calls https addresses reachable from the internet.
            'webhook_url' => ['nullable', 'string', 'max:255', 'url:https'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'bank_id.regex' => 'Mã ngân hàng chỉ gồm chữ và số (VD: VCB, MB, 970436).',
            'account_number.regex' => 'Số tài khoản chỉ gồm chữ số.',
            'sepay_webhook_api_key.min' => 'API key cần ít nhất 16 ký tự.',
            'sepay_webhook_api_key.regex' => 'API key không được chứa khoảng trắng.',
            'webhook_url.url' => 'URL webhook phải là địa chỉ https, VD: https://ten-may.ts.net/webhooks/sepay.',
        ];
    }
}
