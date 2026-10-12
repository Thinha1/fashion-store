<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Since the 01/07/2025 reorganisation Vietnamese addresses are province
     * -> ward, with no district. New addresses and orders leave
     * district_name empty; older rows keep the district they were saved with.
     */
    public function up(): void
    {
        foreach (['addresses', 'orders'] as $table) {
            Schema::table($table, function (Blueprint $table): void {
                $table->string('district_name')->nullable()->change();
            });
        }
    }

    /**
     * Rows without a district get an empty string so the column can be
     * required again.
     */
    public function down(): void
    {
        foreach (['addresses', 'orders'] as $table) {
            DB::table($table)->whereNull('district_name')->update(['district_name' => '']);

            Schema::table($table, function (Blueprint $table): void {
                $table->string('district_name')->nullable(false)->change();
            });
        }
    }
};
