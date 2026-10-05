<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'category_id', 'brand_id', 'name', 'slug', 'description',
    'base_price', 'status', 'is_featured', 'created_by', 'updated_by',
])]
class Product extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_LABELS = [
        'active' => 'Đang kinh doanh',
        'archived' => 'Không kinh doanh',
    ];

    protected function casts(): array
    {
        return [
            'base_price' => 'decimal:2',
            'is_featured' => 'boolean',
        ];
    }

    /**
     * Active products with at least one sellable variant at or below its own low-stock threshold.
     */
    public function scopeLowStock(Builder $query): void
    {
        $query->where('status', 'active')->whereHas('variants', fn (Builder $variants) => $variants
            ->where('is_active', true)
            ->where('stock_quantity', '>', 0)
            ->whereColumn('stock_quantity', '<=', 'low_stock_threshold'));
    }

    /**
     * Active products that have variants but none of them can be sold right now.
     */
    public function scopeOutOfStock(Builder $query): void
    {
        $query->where('status', 'active')
            ->whereHas('variants')
            ->whereDoesntHave('variants', fn (Builder $variants) => $variants
                ->where('is_active', true)->where('stock_quantity', '>', 0));
    }

    public function scopeWithoutImages(Builder $query): void
    {
        $query->where('status', 'active')->whereDoesntHave('images');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function wishlists(): HasMany
    {
        return $this->hasMany(Wishlist::class);
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
