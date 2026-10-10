<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subtotal = fake()->randomElement([299000, 499000, 899000]);
        $shippingFee = 30000;

        return [
            'order_number' => 'DH'.now()->format('ymd').strtoupper(Str::random(6)),
            'user_id' => User::factory(),
            'status' => 'pending',
            'status_history' => [],
            'payment_method' => 'cod',
            'payment_status' => 'unpaid',
            'customer_name' => fake()->name(),
            'customer_email' => fake()->safeEmail(),
            'customer_phone' => '0901234567',
            'province_name' => 'TP. Hồ Chí Minh',
            'district_name' => 'Quận 1',
            'ward_name' => 'Phường Bến Nghé',
            'shipping_address' => '12 Lê Lợi',
            'subtotal' => $subtotal,
            'discount_amount' => 0,
            'shipping_fee' => $shippingFee,
            'grand_total' => $subtotal + $shippingFee,
            'placed_at' => now(),
        ];
    }

    public function status(string $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }
}
