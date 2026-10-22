<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * The one saved bank-transfer configuration (receiving account + SePay
 * webhook key). Read through App\Services\Payments\BankTransfer, never
 * directly — that service applies the "empty means fall back to .env" rule.
 */
#[Fillable(['bank_id', 'account_number', 'account_name', 'sepay_webhook_api_key', 'webhook_url', 'updated_by'])]
class PaymentSetting extends Model
{
    /**
     * VietQR bank codes offered in the settings form (api.vietqr.io/v2/banks);
     * any other code or BIN can still be typed in.
     */
    public const BANKS = [
        'VCB' => 'Vietcombank', 'ICB' => 'VietinBank', 'BIDV' => 'BIDV', 'VBA' => 'Agribank',
        'TCB' => 'Techcombank', 'MB' => 'MBBank', 'ACB' => 'ACB', 'VPB' => 'VPBank', 'TPB' => 'TPBank',
        'STB' => 'Sacombank', 'VIB' => 'VIB', 'HDB' => 'HDBank', 'SHB' => 'SHB', 'MSB' => 'MSB',
        'OCB' => 'OCB', 'LPB' => 'LPBank', 'SEAB' => 'SeABank', 'EIB' => 'Eximbank', 'CAKE' => 'CAKE', 'TIMO' => 'Timo',
    ];

    protected function casts(): array
    {
        return [
            'sepay_webhook_api_key' => 'encrypted',
        ];
    }

    public static function current(): ?self
    {
        return static::query()->oldest('id')->first();
    }

    /**
     * Only ever the last four characters — safe to render in the admin UI.
     */
    public function maskedApiKey(): ?string
    {
        $apiKey = $this->sepay_webhook_api_key;
        if (! $apiKey) {
            return null;
        }

        return Str::length($apiKey) <= 4 ? str_repeat('•', 8) : str_repeat('•', 8).Str::substr($apiKey, -4);
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
