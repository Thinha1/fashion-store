<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

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

        // Vai trò nhân viên mặc định (Quản lý cửa hàng, Bán hàng, Kho, Nội
        // dung) do migration refine_permission_catalog tạo; "staff" giữ lại
        // làm vai trò trống. Phân quyền và tài khoản: /admin/vai-tro, /admin/nhan-vien.

        User::query()->updateOrCreate(['email' => 'admin@example.com'], [
            'role_id' => $roles['admin']->id,
            'name' => 'Admin Fashion Store',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
        ]);

        if (app()->environment(['local', 'testing'])) {
            $this->call([CatalogDemoSeeder::class, CouponDemoSeeder::class, SizeChartSeeder::class]);
        }

        // One demo account per default staff role, to try the permissions.
        if (app()->environment('local')) {
            $demoStaff = [
                'quanly@example.com' => ['Quản lý Demo', 'store-manager'],
                'banhang@example.com' => ['Bán hàng Demo', 'sales'],
                'kho@example.com' => ['Kho Demo', 'warehouse'],
                'noidung@example.com' => ['Nội dung Demo', 'content'],
            ];
            foreach ($demoStaff as $email => [$name, $roleCode]) {
                $roleId = Role::query()->where('code', $roleCode)->value('id');
                if ($roleId !== null) {
                    User::query()->updateOrCreate(['email' => $email], [
                        'role_id' => $roleId, 'name' => $name, 'email_verified_at' => now(), 'password' => Hash::make('password'),
                    ]);
                }
            }
        }
    }
}
