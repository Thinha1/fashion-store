<?php

namespace App\Models;

use App\Models\Concerns\RecordsAuditTrail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'parent_id', 'name', 'slug', 'description', 'image_path', 'is_active', 'sort_order',
    'created_by', 'updated_by',
])]
class Category extends Model
{
    use HasFactory, RecordsAuditTrail;

    /**
     * Products are searchable by their whole category path, so a rename
     * re-indexes every product in this category and the two levels below.
     */
    protected static function booted(): void
    {
        static::updated(function (Category $category): void {
            if (! $category->wasChanged('name')) {
                return;
            }

            $childIds = self::query()->where('parent_id', $category->id)->pluck('id');
            $categoryIds = $childIds
                ->merge(self::query()->whereIn('parent_id', $childIds)->pluck('id'))
                ->push($category->id);

            Product::query()->whereIn('category_id', $categoryIds)->where('status', 'active')->searchable();
        });
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
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
