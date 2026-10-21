<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * "audit.view" gates the read-only /admin/nhat-ky screen. Unlike the
     * settings.manage migration, it is also granted to the existing admin
     * role right away, so admins see the screen without re-running db:seed
     * (DatabaseSeeder still grants every permission to admin on a fresh seed).
     */
    public function up(): void
    {
        $now = now();
        $permissionId = DB::table('permissions')->insertGetId([
            'code' => 'audit.view',
            'name' => 'Xem nhật ký hệ thống',
            'group' => 'system',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $adminRoleId = DB::table('roles')->where('code', 'admin')->value('id');
        if ($adminRoleId !== null) {
            DB::table('permission_role')->insert(['permission_id' => $permissionId, 'role_id' => $adminRoleId]);
        }
    }

    public function down(): void
    {
        DB::table('permissions')->where('code', 'audit.view')->delete();
    }
};
