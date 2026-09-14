<?php

namespace Database\Factories;

use App\Models\Permission;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Permission>
 */
class PermissionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $code = fake()->unique()->word().'.'.Str::random(4);

        return [
            'code' => $code,
            'name' => ucfirst($code),
            'group' => fake()->randomElement(['system', 'catalog', 'inventory', 'orders', 'customers']),
        ];
    }
}
