<?php

namespace App\Models;

use App\Models\Concerns\RecordsAuditTrail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'product_variant_id', 'code', 'name', 'scope', 'discount_type', 'discount_value',
    'max_discount_amount', 'min_order_amount', 'usage_limit', 'used_count',
    'usage_limit_per_customer', 'starts_at', 'ends_at', 'is_active', 'is_auto_apply',
    'created_by', 'updated_by',
])]
class Discount extends Model
{
    use HasFactory, RecordsAuditTrail;

    /**
     * What a discount lowers: one variant's unit price, the order subtotal
     * (an order voucher) or the shipping fee (a shipping voucher). An order
     * holds at most one voucher of each kind.
     */
    public const SCOPE_LABELS = [
        'variant' => 'Giảm theo biến thể',
        'order' => 'Voucher giảm đơn',
        'shipping' => 'Voucher giảm phí ship',
    ];

    public const VOUCHER_SCOPES = ['order', 'shipping'];

    protected function casts(): array
    {
        return [
            'discount_value' => 'decimal:2',
            'max_discount_amount' => 'decimal:2',
            'min_order_amount' => 'decimal:2',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
            'is_auto_apply' => 'boolean',
        ];
    }

    public function isVoucher(): bool
    {
        return in_array($this->scope, self::VOUCHER_SCOPES, true);
    }

    /**
     * How the voucher is shown to the customer: its name, else its code.
     */
    public function voucherLabel(): string
    {
        return $this->name ?: (string) $this->code;
    }

    /**
     * Running, upcoming, expired or switched off, as on the admin list.
     */
    public function lifecycle(): string
    {
        return match (true) {
            ! $this->is_active => 'disabled',
            $this->starts_at?->isFuture() => 'upcoming',
            $this->ends_at?->isPast() => 'expired',
            default => 'running',
        };
    }

    /**
     * Enabled and inside its starts_at..ends_at window right now.
     *
     * @param  Builder<Discount>  $query
     */
    public function scopeCurrentlyActive(Builder $query): void
    {
        $query->where('is_active', true)
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now());
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function shippingOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'shipping_discount_id');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
