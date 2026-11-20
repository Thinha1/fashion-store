<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The returns feature (/doi-tra) is dropped from the project: its table never
 * got a screen, and its permission granted nothing. Revoking the permission
 * also clears it from every role (permission_role cascades).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('return_requests');
        DB::table('permissions')->where('code', 'returns.manage')->delete();
    }

    /** Brings back the empty table and the permission; roles must be granted it again. */
    public function down(): void
    {
        (require __DIR__.'/2026_09_07_001700_create_return_requests_table.php')->up();

        DB::table('permissions')->insertOrIgnore([
            'code' => 'returns.manage', 'name' => 'Duyệt yêu cầu đổi/trả', 'group' => 'orders',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
};
