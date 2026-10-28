<?php

namespace App\Models;

use App\Models\Concerns\RecordsAuditTrail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A size chart for a brand and/or a category subtree — which one a product
 * page shows is decided by App\Services\Catalog\SizeChartResolver.
 *
 * `rows` holds one entry per size: {size, height_min, height_max,
 * weight_min, weight_max, values}, where `values` lines up with `columns`
 * (the extra measurements, e.g. chest and length in cm). Height/weight are
 * what the "gợi ý size" on the product page reads.
 */
#[Fillable(['name', 'brand_id', 'category_id', 'columns', 'rows', 'note'])]
class SizeChart extends Model
{
    use HasFactory, RecordsAuditTrail;

    protected function casts(): array
    {
        return [
            'columns' => 'array',
            'rows' => 'array',
        ];
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Products that picked this chart by hand (not the ones it applies to by brand/category).
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * "Coolmate · Áo nam", "Mọi thương hiệu · Mọi danh mục" — what the chart covers.
     */
    public function coverageLabel(): string
    {
        return ($this->brand?->name ?? 'Mọi thương hiệu').' · '.($this->category?->name ?? 'Mọi danh mục');
    }

    /**
     * Whether any row carries a height or weight range, i.e. the chart can suggest a size.
     */
    public function canSuggest(): bool
    {
        foreach ($this->rows ?? [] as $row) {
            if (($row['height_min'] ?? null) !== null || ($row['weight_min'] ?? null) !== null) {
                return true;
            }
        }

        return false;
    }
}
