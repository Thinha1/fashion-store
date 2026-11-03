<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['product_id', 'user_id', 'order_item_id', 'rating', 'comment', 'status', 'updated_by'])]
class Review extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_HIDDEN = 'hidden';

    public const MIN_RATING = 1;

    public const MAX_RATING = 5;

    /**
     * @var array<string, string>
     */
    public const STATUS_LABELS = [
        self::STATUS_PENDING => 'Chờ duyệt',
        self::STATUS_PUBLISHED => 'Đang hiển thị',
        self::STATUS_HIDDEN => 'Đã ẩn',
    ];

    protected function casts(): array
    {
        return ['rating' => 'integer'];
    }

    public function scopePublished(Builder $query): void
    {
        $query->where('status', self::STATUS_PUBLISHED);
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
