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
    'usage_limit_per_customer', 'starts_at', 'ends_at', 'is_active', 'is_auto_apply', 'targets',
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

    /** What an order voucher can be limited to (the keys of `targets`). */
    public const TARGET_TYPES = ['brand', 'product', 'variant'];

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
            'targets' => 'array',
        ];
    }

    public function isVoucher(): bool
    {
        return in_array($this->scope, self::VOUCHER_SCOPES, true);
    }

    /**
     * The ids an order voucher is limited to, by kind; all empty when it
     * counts the whole order.
     *
     * @return array{brand: list<int>, product: list<int>, variant: list<int>}
     */
    public function targetIds(): array
    {
        $targets = is_array($this->targets) ? $this->targets : [];

        return array_combine(self::TARGET_TYPES, array_map(
            fn (string $type): array => array_values(array_unique(array_map('intval', (array) ($targets[$type] ?? [])))),
            self::TARGET_TYPES,
        ));
    }

    public function hasTargets(): bool
    {
        return array_filter($this->targetIds()) !== [];
    }

    /**
     * Whether a cart line for this variant counts towards the voucher: always
     * when it has no targets, else when the variant, its product or its brand
     * is one of them.
     */
    public function appliesToVariant(ProductVariant $variant): bool
    {
        if (! $this->hasTargets()) {
            return true;
        }

        $ids = $this->targetIds();

        return in_array((int) $variant->id, $ids['variant'], true)
            || in_array((int) $variant->product_id, $ids['product'], true)
            || in_array((int) $variant->product?->brand_id, $ids['brand'], true);
    }

    /**
     * "thương hiệu Coolmate, Levents; 3 sản phẩm" — what the voucher is
     * limited to, for the admin list and the customer; null when it counts
     * the whole order.
     */
    public function targetSummary(): ?string
    {
        if (! $this->hasTargets()) {
            return null;
        }

        $ids = $this->targetIds();
        $brands = $ids['brand'] === [] ? collect() : Brand::query()->whereIn('id', $ids['brand'])->orderBy('name')->pluck('name');
        $parts = [];

        if ($brands->isNotEmpty()) {
            $parts[] = 'thương hiệu '.$brands->take(2)->implode(', ').($brands->count() > 2 ? ' và '.($brands->count() - 2).' thương hiệu khác' : '');
        }
        if ($ids['product'] !== []) {
            $parts[] = count($ids['product']).' sản phẩm';
        }
        if ($ids['variant'] !== []) {
            $parts[] = count($ids['variant']).' biến thể';
        }

        return implode('; ', $parts);
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
