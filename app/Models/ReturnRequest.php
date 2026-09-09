<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'request_number', 'order_item_id', 'user_id', 'type', 'quantity', 'reason',
    'image_paths', 'status', 'resolution', 'restock_quantity', 'admin_note',
    'reviewed_by', 'reviewed_at',
])]
class ReturnRequest extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'image_paths' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
