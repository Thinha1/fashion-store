<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProductVariant>
 */
class ProductVariantFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $size = fake()->randomElement(ProductVariant::SIZES);
        $color = fake()->randomElement(['Đen', 'Trắng', 'Xanh', 'Đỏ', 'Be']);

        return [
            'product_id' => Product::factory(),
            'size' => $size,
            'color' => $color,
            'sku' => 'SKU-'.strtoupper(Str::random(8)),
            'price' => fake()->randomElement([199000, 299000, 399000, 499000]),
            'stock_quantity' => fake()->numberBetween(0, 50),
            'low_stock_threshold' => 5,
            'is_active' => true,
        ];
    }

    public function outOfStock(): static
    {
        return $this->state(fn () => ['stock_quantity' => 0]);
    }

    public function lowStock(int $threshold = 2): static
    {
        return $this->state(fn () => [
            'stock_quantity' => $threshold,
            'low_stock_threshold' => $threshold + 1,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
