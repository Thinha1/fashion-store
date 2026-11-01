<?php

namespace Database\Factories;

use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Review>
 */
class ReviewFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'user_id' => User::factory(),
            'order_item_id' => OrderItem::factory(),
            'rating' => fake()->numberBetween(Review::MIN_RATING, Review::MAX_RATING),
            'comment' => fake()->sentence(),
            'status' => Review::STATUS_PUBLISHED,
        ];
    }

    public function hidden(): static
    {
        return $this->state(fn () => ['status' => Review::STATUS_HIDDEN]);
    }
}
