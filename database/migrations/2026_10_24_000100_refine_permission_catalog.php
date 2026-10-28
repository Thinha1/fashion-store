<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * New, finer-grained permissions. Each one is granted to every role that
     * held the permission it was split from, so nobody loses access when
     * this runs on an existing database.
     *
     * @var array<string, array{name: string, group: string, from: string}>
     */
    private const SPLITS = [
        'dashboard.view' => ['name' => 'Xem dashboard doanh thu', 'group' => 'system', 'from' => 'admin.access'],
        'roles.manage' => ['name' => 'Tạo vai trò và chọn quyền cho vai trò', 'group' => 'system', 'from' => 'staff.manage'],
        'settings.ai' => ['name' => 'Cấu hình AI', 'group' => 'system', 'from' => 'settings.manage'],
        'settings.payment' => ['name' => 'Cấu hình thanh toán QR và webhook SePay', 'group' => 'system', 'from' => 'settings.manage'],
        'catalog.manage' => ['name' => 'Quản lý thương hiệu, danh mục, bảng size', 'group' => 'catalog', 'from' => 'products.manage'],
        'discounts.manage' => ['name' => 'Quản lý giảm giá theo biến thể', 'group' => 'catalog', 'from' => 'products.manage'],
        'ai.assistant' => ['name' => 'Dùng trợ lý AI soạn và sửa sản phẩm', 'group' => 'catalog', 'from' => 'products.manage'],
        'inventory.confirm' => ['name' => 'Xác nhận phiếu nhập (cộng tồn kho)', 'group' => 'inventory', 'from' => 'inventory.manage'],
        'orders.view' => ['name' => 'Xem danh sách và chi tiết đơn hàng', 'group' => 'orders', 'from' => 'orders.manage'],
        'payments.confirm' => ['name' => 'Xác nhận đã nhận tiền chuyển khoản, hủy đơn đã thanh toán', 'group' => 'orders', 'from' => 'orders.manage'],
    ];

    /**
     * Clearer names for the permissions that stay.
     *
     * @var array<string, string>
     */
    private const NAMES = [
        'admin.access' => 'Vào khu quản trị',
        'products.manage' => 'Quản lý sản phẩm, biến thể, ảnh',
        'suppliers.manage' => 'Quản lý nhà cung cấp',
        'inventory.manage' => 'Lập và sửa phiếu nhập nháp',
        'orders.manage' => 'Chuyển trạng thái đơn hàng',
        'returns.manage' => 'Duyệt yêu cầu đổi/trả',
        'reviews.manage' => 'Duyệt và ẩn đánh giá sản phẩm',
        'coupons.manage' => 'Quản lý mã giảm giá theo đơn',
        'staff.manage' => 'Quản lý tài khoản nhân viên, gán vai trò',
        'audit.view' => 'Xem nhật ký hệ thống',
    ];

    /**
     * Default staff roles: code => [name, description, permission codes].
     *
     * @var array<string, array{0: string, 1: string, 2: list<string>}>
     */
    private const ROLES = [
        'store-manager' => ['Quản lý cửa hàng', 'Vận hành toàn bộ cửa hàng, trừ phân quyền, nhân sự và cấu hình hệ thống.', [
            'admin.access', 'dashboard.view', 'audit.view', 'catalog.manage', 'products.manage', 'discounts.manage', 'ai.assistant',
            'reviews.manage', 'coupons.manage', 'suppliers.manage', 'inventory.manage', 'inventory.confirm', 'orders.view',
            'orders.manage', 'payments.confirm', 'returns.manage', 'customers.view',
        ]],
        'sales' => ['Nhân viên bán hàng', 'Xử lý đơn hàng và chăm sóc khách hàng.', ['admin.access', 'orders.view', 'orders.manage', 'customers.view']],
        'warehouse' => ['Nhân viên kho', 'Nhà cung cấp và phiếu nhập nháp; việc xác nhận nhập kho do quản lý duyệt.', ['admin.access', 'suppliers.manage', 'inventory.manage']],
        'content' => ['Nhân viên nội dung', 'Soạn và cập nhật catalog, duyệt đánh giá.', ['admin.access', 'catalog.manage', 'products.manage', 'ai.assistant', 'reviews.manage']],
    ];

    public function up(): void
    {
        $now = now();

        DB::transaction(function () use ($now): void {
            foreach (self::SPLITS as $code => $split) {
                $id = DB::table('permissions')->insertGetId(['code' => $code, 'name' => $split['name'], 'group' => $split['group'], 'created_at' => $now, 'updated_at' => $now]);
                $fromId = DB::table('permissions')->where('code', $split['from'])->value('id');
                $roleIds = $fromId === null ? [] : DB::table('permission_role')->where('permission_id', $fromId)->pluck('role_id');
                DB::table('permission_role')->insert(collect($roleIds)->map(fn (int $roleId): array => ['permission_id' => $id, 'role_id' => $roleId])->all());
            }

            DB::table('permissions')->where('code', 'customers.manage')
                ->update(['code' => 'customers.view', 'name' => 'Xem khách hàng và lịch sử mua', 'updated_at' => $now]);
            // Both replaced: settings.manage by settings.ai/settings.payment,
            // audit_logs.view by audit.view. The pivot rows go with them.
            DB::table('permissions')->whereIn('code', ['settings.manage', 'audit_logs.view'])->delete();

            foreach (self::NAMES as $code => $name) {
                DB::table('permissions')->where('code', $code)->update(['name' => $name, 'updated_at' => $now]);
            }

            $ids = DB::table('permissions')->pluck('id', 'code');
            foreach (self::ROLES as $code => [$name, $description, $permissions]) {
                if (DB::table('roles')->where('code', $code)->exists()) {
                    continue;
                }
                $roleId = DB::table('roles')->insertGetId(['code' => $code, 'name' => $name, 'description' => $description, 'created_at' => $now, 'updated_at' => $now]);
                DB::table('permission_role')->insert(array_map(fn (string $permission): array => ['permission_id' => $ids[$permission], 'role_id' => $roleId], $permissions));
            }

            // Admin always holds the whole catalog.
            $adminId = DB::table('roles')->where('code', 'admin')->value('id');
            if ($adminId !== null) {
                $held = DB::table('permission_role')->where('role_id', $adminId)->pluck('permission_id')->all();
                DB::table('permission_role')->insert($ids->values()->diff($held)->map(fn (int $id): array => ['permission_id' => $id, 'role_id' => $adminId])->values()->all());
            }
        });
    }

    public function down(): void
    {
        $now = now();
        DB::transaction(function () use ($now): void {
            $settingsId = DB::table('permissions')->insertGetId(['code' => 'settings.manage', 'name' => 'Quản lý cấu hình hệ thống (AI)', 'group' => 'system', 'created_at' => $now, 'updated_at' => $now]);
            $roleIds = DB::table('permission_role')
                ->whereIn('permission_id', DB::table('permissions')->whereIn('code', ['settings.ai', 'settings.payment'])->pluck('id'))
                ->distinct()->pluck('role_id');
            DB::table('permission_role')->insert($roleIds->map(fn (int $roleId): array => ['permission_id' => $settingsId, 'role_id' => $roleId])->all());

            DB::table('permissions')->insert(['code' => 'audit_logs.view', 'name' => 'Xem nhật ký thao tác quản trị', 'group' => 'system', 'created_at' => $now, 'updated_at' => $now]);
            DB::table('permissions')->where('code', 'customers.view')->update(['code' => 'customers.manage', 'name' => 'Xem khách hàng & lịch sử mua']);
            DB::table('permissions')->whereIn('code', array_keys(self::SPLITS))->delete();
            DB::table('roles')->whereIn('code', array_keys(self::ROLES))->whereNotExists(
                fn ($query) => $query->select(DB::raw(1))->from('users')->whereColumn('users.role_id', 'roles.id'),
            )->delete();
        });
    }
};
