<?php

namespace Database\Factories;

use App\Models\Address;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Address>
 */
class AddressFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'label' => fake()->optional()->randomElement(['Nhà riêng', 'Công ty']),
            'recipient_name' => fake()->name(),
            'phone' => '09'.fake()->numerify('########'),
            'province_name' => 'TP. Hồ Chí Minh',
            'district_name' => fake()->randomElement(['Quận 1', 'Quận 3', 'Quận Bình Thạnh']),
            'ward_name' => fake()->randomElement(['Phường Bến Nghé', 'Phường 6', 'Phường 25']),
            'address_line' => fake()->buildingNumber().' Lê Lợi',
            'is_default' => false,
        ];
    }

    public function default(): static
    {
        return $this->state(fn () => ['is_default' => true]);
    }
}
