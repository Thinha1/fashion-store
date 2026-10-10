<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'order_number', 'user_id', 'discount_id', 'status',
    'status_history', 'payment_method', 'payment_status', 'transaction_code',
    'payment_reviewed_by', 'payment_reviewed_at', 'customer_name',
    'customer_email', 'customer_phone', 'province_name', 'district_name',
    'ward_name', 'shipping_address', 'customer_note', 'subtotal',
    'discount_amount', 'shipping_fee', 'grand_total', 'placed_at', 'updated_by',
])]
class Order extends Model
{
    use HasFactory;

    /**
     * Order lifecycle (BUSINESS_FLOWS.md §2), in display order.
     */
    public const STATUS_LABELS = [
        'pending' => 'Chờ xác nhận',
        'confirmed' => 'Đã xác nhận',
        'preparing' => 'Đang chuẩn bị hàng',
        'shipping' => 'Đang giao hàng',
        'delivered' => 'Đã giao hàng',
        'cancelled' => 'Đã hủy',
        'returned' => 'Đã trả hàng',
    ];

    /**
     * Where staff can move an order from each status — one step at a time,
     * never skipping (BUSINESS_FLOWS.md §2). `delivered → returned` belongs
     * to the return-request flow, not here.
     */
    public const STAFF_TRANSITIONS = [
        'pending' => ['confirmed', 'cancelled'],
        'confirmed' => ['preparing', 'cancelled'],
        'preparing' => ['shipping'],
        'shipping' => ['delivered'],
    ];

    /**
     * Button labels for moving an order INTO each status.
     */
    public const TRANSITION_LABELS = [
        'confirmed' => 'Xác nhận đơn',
        'preparing' => 'Chuẩn bị hàng',
        'shipping' => 'Giao cho vận chuyển',
        'delivered' => 'Đã giao thành công',
        'cancelled' => 'Hủy đơn',
    ];

    public const PAYMENT_METHOD_LABELS = [
        'cod' => 'Thanh toán khi nhận hàng (COD)',
        'bank_transfer' => 'Chuyển khoản ngân hàng',
    ];

    public const PAYMENT_STATUS_LABELS = [
        'unpaid' => 'Chưa thanh toán',
        'paid' => 'Đã thanh toán',
        'refunded' => 'Đã hoàn tiền',
    ];

    /**
     * @return list<string>
     */
    public function nextStaffStatuses(): array
    {
        return self::STAFF_TRANSITIONS[$this->status] ?? [];
    }

    /**
     * Statuses staff may cancel from (customers: `pending` only).
     *
     * @return list<string>
     */
    public static function staffCancellableStatuses(): array
    {
        return array_keys(array_filter(
            self::STAFF_TRANSITIONS,
            fn (array $targets): bool => in_array('cancelled', $targets, true),
        ));
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function paymentMethodLabel(): string
    {
        return self::PAYMENT_METHOD_LABELS[$this->payment_method] ?? $this->payment_method;
    }

    public function paymentStatusLabel(): string
    {
        return self::PAYMENT_STATUS_LABELS[$this->payment_status] ?? $this->payment_status;
    }

    protected function casts(): array
    {
        return [
            'status_history' => 'array',
            'payment_reviewed_at' => 'datetime',
            'placed_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'shipping_fee' => 'decimal:2',
            'grand_total' => 'decimal:2',
        ];
    }

    /**
     * Street, ward, (district for older orders), province.
     */
    public function fullShippingAddress(): string
    {
        return implode(', ', array_filter([$this->shipping_address, $this->ward_name, $this->district_name, $this->province_name], 'filled'));
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

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
