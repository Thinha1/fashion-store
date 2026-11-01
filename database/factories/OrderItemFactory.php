<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $price = fake()->randomElement([199000, 299000, 399000]);

        return [
            'order_id' => Order::factory(),
            'product_variant_id' => ProductVariant::factory(),
            'product_name' => fake()->words(3, true),
            'sku' => 'SKU-'.strtoupper(fake()->bothify('????####')),
            'size_name' => 'M',
            'color_name' => 'Đen',
            'original_unit_price' => $price,
            'discount_amount' => 0,
            'unit_price' => $price,
            'quantity' => 1,
            'line_total' => $price,
        ];
    }
}
