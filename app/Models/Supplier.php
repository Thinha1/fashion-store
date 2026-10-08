<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'phone', 'email', 'address', 'tax_code', 'is_active', 'created_by', 'updated_by'])]
class Supplier extends Model
{
    use HasFactory;

    /**
     * The code needs the auto-increment id, so it can only be written once the row exists.
     */
    protected static function booted(): void
    {
        static::created(function (Supplier $supplier): void {
            $supplier->forceFill(['code' => self::codeFor($supplier->id)])->saveQuietly();
        });
    }

    public static function codeFor(int $id): string
    {
        return 'NCC-'.str_pad((string) $id, 4, '0', STR_PAD_LEFT);
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function goodsReceipts(): HasMany
    {
        return $this->hasMany(GoodsReceipt::class);
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
