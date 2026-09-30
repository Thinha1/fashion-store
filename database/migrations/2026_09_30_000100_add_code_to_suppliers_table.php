<?php

use App\Models\Supplier;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Human-readable supplier code (NCC-0001), derived from the auto-increment
     * id so it is unique without a counter table. Existing suppliers are
     * backfilled; new ones get theirs from Supplier::booted().
     */
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->string('code', 20)->nullable()->unique()->after('id');
        });

        DB::table('suppliers')->orderBy('id')->each(
            fn (object $supplier) => DB::table('suppliers')->where('id', $supplier->id)->update(['code' => Supplier::codeFor($supplier->id)])
        );
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn('code');
        });
    }
};
