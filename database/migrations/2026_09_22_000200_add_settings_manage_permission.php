<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Adds one permission to the catalog seeded by
     * 2026_09_14_000100_create_permissions_table.php — the "settings.manage"
     * gate for the new /admin/cai-dat/ai screen. Deliberately its own
     * permission rather than reusing products.manage/staff.manage: an API
     * key is a different trust tier than catalog editing, and staff who
     * manage products/inventory shouldn't automatically be able to see or
     * rotate it. Only inserts the row — same as the original migration,
     * granting it to existing roles is DatabaseSeeder's job on the next
     * `db:seed` (see its own comment about newly added permissions).
     */
    public function up(): void
    {
        DB::table('permissions')->insert([
            'code' => 'settings.manage',
            'name' => 'Cấu hình hệ thống (agent AI, ...)',
            'group' => 'system',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('permissions')->where('code', 'settings.manage')->delete();
    }
};
