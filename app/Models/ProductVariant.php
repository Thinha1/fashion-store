<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'product_id', 'size', 'color', 'sku', 'price', 'stock_quantity',
    'low_stock_threshold', 'is_active', 'created_by', 'updated_by',
])]
class ProductVariant extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Standard apparel sizes offered across the catalog, in display order.
     */
    public const SIZES = ['S', 'M', 'L', 'XL', '2XL', '3XL', '4XL'];

    /**
     * Raw SQL to sort variants by the canonical size order above instead of
     * alphabetically (which would put "2XL" before "L", "M", "S"...).
     */
    public static function sizeOrderRaw(): string
    {
        $cases = implode(' ', array_map(
            fn (int $i, string $size): string => "WHEN '{$size}' THEN {$i}",
            array_keys(self::SIZES),
            self::SIZES,
        ));

        return "CASE size {$cases} ELSE ".count(self::SIZES).' END';
    }

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class);
    }

    public function discounts(): HasMany
    {
        return $this->hasMany(Discount::class);
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function goodsReceiptItems(): HasMany
    {
        return $this->hasMany(GoodsReceiptItem::class);
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
