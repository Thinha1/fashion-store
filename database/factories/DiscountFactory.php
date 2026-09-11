<?php

namespace Database\Factories;

use App\Models\Discount;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Discount>
 */
class DiscountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_variant_id' => ProductVariant::factory(),
            'scope' => 'variant',
            'discount_type' => 'percent',
            'discount_value' => 10,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
            'is_active' => true,
        ];
    }

    public function coupon(): static
    {
        return $this->state(fn () => [
            'scope' => 'order',
            'code' => 'COUPON-'.strtoupper(Str::random(6)),
            'discount_type' => 'fixed',
            'discount_value' => 50000,
        ]);
    }
}
