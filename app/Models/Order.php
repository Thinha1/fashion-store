<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'order_number', 'user_id', 'guest_access_token_hash', 'discount_id', 'status',
    'status_history', 'payment_method', 'payment_status', 'transaction_code',
    'payment_proof_path', 'payment_proof_submitted_at', 'payment_reviewed_by',
    'payment_reviewed_at', 'payment_rejection_reason', 'customer_name',
    'customer_email', 'customer_phone', 'province_name', 'district_name',
    'ward_name', 'shipping_address', 'customer_note', 'subtotal',
    'discount_amount', 'shipping_fee', 'grand_total', 'placed_at',
])]
class Order extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status_history' => 'array',
            'payment_proof_submitted_at' => 'datetime',
            'payment_reviewed_at' => 'datetime',
            'placed_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'shipping_fee' => 'decimal:2',
            'grand_total' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function discount(): BelongsTo
    {
        return $this->belongsTo(Discount::class);
    }

    public function paymentReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payment_reviewed_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
