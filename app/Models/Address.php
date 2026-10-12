<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

#[Fillable([
    'user_id', 'label', 'recipient_name', 'phone', 'province_code',
    'province_name', 'district_name', 'ward_name', 'address_line', 'is_default',
])]
class Address extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * "12 Lê Lợi, Phường Sài Gòn, Thành phố Hồ Chí Minh" — older addresses
     * also carry the district they were saved with.
     */
    public function fullAddress(): string
    {
        return implode(', ', array_filter([$this->address_line, $this->ward_name, $this->district_name, $this->province_name], 'filled'));
    }

    /**
     * Make this the owner's only default address.
     */
    public function markAsDefault(): void
    {
        DB::transaction(function (): void {
            static::query()
                ->where('user_id', $this->user_id)
                ->whereKeyNot($this->id)
                ->update(['is_default' => false]);

            $this->forceFill(['is_default' => true])->save();
        });
    }
}
