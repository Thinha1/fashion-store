<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Danh mục quyền ban đầu, bám theo các mã đã dùng trong code
     * (`routes/admin.php`, Blade layout) cộng các màn quản trị đã lên kế
     * hoạch ở IMPLEMENTATION_SPEC.md Giai đoạn 5 nhưng chưa code tới.
     *
     * @var list<array{code: string, name: string, group: string}>
     */
    private const CATALOG = [
        ['code' => 'admin.access', 'name' => 'Truy cập trang quản trị', 'group' => 'system'],
        ['code' => 'products.manage', 'name' => 'Quản lý thương hiệu, danh mục, sản phẩm, biến thể, giảm giá biến thể', 'group' => 'catalog'],
        ['code' => 'suppliers.manage', 'name' => 'Quản lý nhà cung cấp', 'group' => 'inventory'],
        ['code' => 'inventory.manage', 'name' => 'Quản lý phiếu nhập & tồn kho', 'group' => 'inventory'],
        ['code' => 'orders.manage', 'name' => 'Xử lý trạng thái đơn hàng', 'group' => 'orders'],
        ['code' => 'payments.manage', 'name' => 'Duyệt chứng từ chuyển khoản (fallback thủ công SePay)', 'group' => 'orders'],
        ['code' => 'returns.manage', 'name' => 'Duyệt yêu cầu đổi/trả', 'group' => 'orders'],
        ['code' => 'reviews.manage', 'name' => 'Duyệt/ẩn đánh giá sản phẩm', 'group' => 'catalog'],
        ['code' => 'coupons.manage', 'name' => 'Quản lý mã giảm giá (coupon, discounts.scope=order)', 'group' => 'catalog'],
        ['code' => 'staff.manage', 'name' => 'Quản lý tài khoản & phân quyền nhân viên', 'group' => 'system'],
        ['code' => 'customers.manage', 'name' => 'Xem khách hàng & lịch sử mua', 'group' => 'customers'],
        ['code' => 'audit_logs.view', 'name' => 'Xem nhật ký thao tác quản trị', 'group' => 'system'],
    ];

    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('group')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('permission_role', function (Blueprint $table) {
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->primary(['permission_id', 'role_id']);
        });

        $now = now();
        DB::table('permissions')->insert(array_map(
            static fn (array $permission): array => [...$permission, 'created_at' => $now, 'updated_at' => $now],
            self::CATALOG,
        ));

        $this->migrateExistingJsonPermissionsToPivot();

        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn('permissions');
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->json('permissions')->nullable();
        });

        // Không khôi phục lại dữ liệu JSON cũ khi rollback — chấp nhận mất
        // mát ở giai đoạn dev; roles.permissions sẽ về lại rỗng cho mọi role.
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('permissions');
    }

    /**
     * Roles hiện có (nếu migration này chạy trên DB đã có dữ liệu) từng lưu
     * quyền dạng mảng JSON trong roles.permissions; gán mã quyền hợp lệ sang
     * pivot mới. Wildcard "*" (role admin cũ) được bỏ qua ở đây — role admin
     * được gán toàn bộ danh mục quyền trực tiếp trong DatabaseSeeder.
     */
    private function migrateExistingJsonPermissionsToPivot(): void
    {
        $permissionIdsByCode = DB::table('permissions')->pluck('id', 'code');

        DB::table('roles')->get(['id', 'permissions'])->each(function (object $role) use ($permissionIdsByCode): void {
            $codes = json_decode((string) $role->permissions, true) ?: [];

            $rows = collect($codes)
                ->filter(static fn ($code): bool => is_string($code) && $code !== '*' && $permissionIdsByCode->has($code))
                ->map(static fn (string $code): array => [
                    'role_id' => $role->id,
                    'permission_id' => $permissionIdsByCode[$code],
                ])
                ->all();

            if ($rows !== []) {
                DB::table('permission_role')->insert($rows);
            }
        });
    }
};
