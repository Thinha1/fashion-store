<?php

namespace Database\Factories;

use App\Models\Permission;
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
        ];
    }

    /**
     * Role with every permission in the catalog attached — behaves like the
     * seeded "admin" role (see DatabaseSeeder).
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes): array => [
            'name' => 'Quản trị viên',
            'code' => 'admin-'.Str::random(6),
        ])->afterCreating(function (Role $role): void {
            $role->permissions()->sync(Permission::query()->pluck('id'));
        });
    }
}
