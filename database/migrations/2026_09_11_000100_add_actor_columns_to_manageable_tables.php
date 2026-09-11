<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tables that get both `created_by` and `updated_by`: admin/staff-managed
     * catalog & operations content (not customer self-service data, and not
     * immutable log/snapshot rows — see plan §3 "Quy ước chung").
     *
     * @var list<string>
     */
    private const CREATED_AND_UPDATED_BY = [
        'roles', 'brands', 'categories', 'products', 'product_variants',
        'product_images', 'discounts', 'suppliers',
    ];

    /**
     * Tables that only get `updated_by`: the row is created by the customer
     * (or the goods_receipts flow already tracks its own creator), but staff
     * can edit/moderate it afterwards.
     *
     * @var list<string>
     */
    private const UPDATED_BY_ONLY = ['goods_receipts', 'orders', 'reviews'];

    public function up(): void
    {
        foreach (self::CREATED_AND_UPDATED_BY as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->foreignId('created_by')->nullable()->after('id')->constrained('users')->nullOnDelete();
                $blueprint->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            });
        }

        foreach (self::UPDATED_BY_ONLY as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (self::CREATED_AND_UPDATED_BY as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropConstrainedForeignId('created_by');
                $blueprint->dropConstrainedForeignId('updated_by');
            });
        }

        foreach (self::UPDATED_BY_ONLY as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropConstrainedForeignId('updated_by');
            });
        }
    }
};
