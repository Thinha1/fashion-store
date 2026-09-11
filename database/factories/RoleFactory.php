<?php

namespace Database\Factories;

use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Role>
 */
class RoleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $code = fake()->unique()->word().'-'.Str::random(4);

        return [
            'name' => ucfirst($code),
            'code' => $code,
            'permissions' => [],
        ];
    }

    /**
     * Role with the wildcard permission — behaves like the seeded "admin" role.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes): array => [
            'name' => 'Quản trị viên',
            'code' => 'admin-'.Str::random(6),
            'permissions' => ['*'],
        ]);
    }
}
