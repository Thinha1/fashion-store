<?php

namespace Database\Seeders;

use App\Models\Permission;
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
            ['name' => 'Quản trị viên', 'code' => 'admin'],
            ['name' => 'Nhân viên', 'code' => 'staff'],
            ['name' => 'Khách hàng', 'code' => 'customer'],
        ])->mapWithKeys(function (array $role): array {
            $model = Role::query()->updateOrCreate(['code' => $role['code']], $role);

            return [$role['code'] => $model];
        });

        // Admin toàn quyền: gán toàn bộ danh mục quyền hiện có (không phải
        // wildcard) — quyền mới thêm vào danh mục sau này cần seed lại.
        $roles['admin']->permissions()->sync(Permission::query()->pluck('id'));

        // Nhân viên chưa có quyền nào theo mặc định; Admin gán theo nhóm
        // qua Admin/StaffController (chưa hiện thực, xem TEAM_SPLIT.md).

        User::factory()->create([
            'role_id' => $roles['admin']->id,
            'name' => 'Admin Fashion Store',
            'email' => 'admin@example.com',
        ]);
    }
}
