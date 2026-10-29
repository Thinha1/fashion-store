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

    /**
     * A voucher on the shipping fee; free shipping unless the value is changed.
     */
    public function shippingVoucher(): static
    {
        return $this->state(fn () => [
            'product_variant_id' => null,
            'scope' => 'shipping',
            'code' => 'SHIP-'.strtoupper(Str::random(6)),
            'name' => 'Miễn phí vận chuyển',
            'discount_type' => 'percent',
            'discount_value' => 100,
        ]);
    }

    /**
     * Applied to every order that qualifies, with no code to type.
     */
    public function automatic(): static
    {
        return $this->state(fn (array $attributes) => [
            'product_variant_id' => null,
            'code' => null,
            'name' => $attributes['name'] ?? 'Ưu đãi tự động',
            'is_auto_apply' => true,
        ]);
    }
}
