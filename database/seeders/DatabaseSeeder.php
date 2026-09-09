<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $roles = collect([
            ['name' => 'Quản trị viên', 'code' => 'admin', 'permissions' => ['*']],
            ['name' => 'Nhân viên', 'code' => 'staff', 'permissions' => []],
            ['name' => 'Khách hàng', 'code' => 'customer', 'permissions' => []],
        ])->mapWithKeys(function (array $role): array {
            $model = Role::query()->updateOrCreate(['code' => $role['code']], $role);

            return [$role['code'] => $model];
        });

        User::factory()->create([
            'role_id' => $roles['admin']->id,
            'name' => 'Admin Fashion Store',
            'email' => 'admin@example.com',
        ]);
    }
}
