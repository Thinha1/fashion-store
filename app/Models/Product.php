<?php

namespace App\Models;

use App\Models\Concerns\RecordsAuditTrail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Laravel\Scout\Searchable;

#[Fillable([
    'category_id', 'brand_id', 'size_chart_id', 'name', 'slug', 'description',
    'base_price', 'status', 'is_featured', 'created_by', 'updated_by',
])]
class Product extends Model
{
    use HasFactory, RecordsAuditTrail, Searchable, SoftDeletes;

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
     * Only products on sale are worth finding; archiving one drops it from
     * the search index.
     */
    public function shouldBeSearchable(): bool
    {
        return $this->status === 'active';
    }

    /**
     * The text a shopper might type to find this product. Brand, category
     * path ("Nữ Áo Áo sơ mi") and variant colors are denormalized in so
     * "áo sơ mi trắng" matches without a filter.
     *
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        $this->loadMissing(['brand', 'category.parent.parent', 'variants']);

        $categories = [];
        for ($category = $this->category; $category !== null; $category = $category->parent) {
            array_unshift($categories, $category->name);
        }

        $variants = $this->variants->where('is_active', true);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'brand' => $this->brand?->name,
            'category' => implode(' ', $categories),
            'colors' => $variants->pluck('color')->unique()->values()->all(),
            'skus' => $variants->pluck('sku')->values()->all(),
            'description' => Str::limit(trim(strip_tags((string) $this->description)), 2000, ''),
        ];
    }

    /**
     * @param  Builder<Product>  $query
     */
    protected function makeAllSearchableUsing(Builder $query): Builder
    {
        return $query->with(['brand', 'category.parent.parent', 'variants']);
    }

    /**
     * Eager-load what a product card needs to show its real price: active
     * variants and their running discounts (PriceCalculator::priceTag).
     *
     * @param  Builder<Product>  $query
     */
    public function scopeWithListingPrices(Builder $query): void
    {
        $query->with(['variants' => fn ($variants) => $variants->where('is_active', true)->with('activeDiscounts')]);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * The chart picked for this product by hand; null means "automatic" (see SizeChartResolver).
     */
    public function sizeChart(): BelongsTo
    {
        return $this->belongsTo(SizeChart::class);
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
