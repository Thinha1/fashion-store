<?php

namespace App\Actions;

use App\Models\OrderItem;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * A customer rates one line of one of their own orders, once the order is
 * delivered. One review per order line (order_item_id is unique). It waits as
 * `pending` until staff approve it; only published reviews show on the shop.
 */
class SubmitReview
{
    /**
     * @throws ReviewNotAllowedException
     */
    public function execute(OrderItem $item, User $customer, int $rating, ?string $comment): Review
    {
        $item->loadMissing(['order', 'productVariant']);

        if ($item->order->user_id !== $customer->id) {
            throw new ReviewNotAllowedException('Bạn chỉ đánh giá được sản phẩm trong đơn của mình.');
        }

        if ($item->order->status !== 'delivered') {
            throw new ReviewNotAllowedException('Chỉ đánh giá được sản phẩm sau khi đơn hàng đã giao.');
        }

        if ($item->productVariant === null) {
            throw new ReviewNotAllowedException('Sản phẩm này không còn trong cửa hàng nên không thể đánh giá.');
        }

        if ($item->review()->exists()) {
            throw new ReviewNotAllowedException('Bạn đã đánh giá sản phẩm này rồi.');
        }

        try {
            return Review::query()->create([
                'product_id' => $item->productVariant->product_id,
                'user_id' => $customer->id,
                'order_item_id' => $item->id,
                'rating' => $rating,
                'comment' => $comment !== null && trim($comment) !== '' ? trim($comment) : null,
                'status' => Review::STATUS_PENDING,
            ]);
        } catch (UniqueConstraintViolationException) {
            throw new ReviewNotAllowedException('Bạn đã đánh giá sản phẩm này rồi.');
        }
    }
}
