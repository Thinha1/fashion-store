<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which products an order voucher is limited to: {"brand": [ids],
     * "product": [ids], "variant": [ids]}. Null (or all three lists empty)
     * means the voucher counts the whole order. Ids are not foreign keys: a
     * brand or product deleted later simply stops matching anything.
     */
    public function up(): void
    {
        Schema::table('discounts', function (Blueprint $table) {
            $table->json('targets')->nullable()->after('is_auto_apply');
        });
    }

    public function down(): void
    {
        Schema::table('discounts', function (Blueprint $table) {
            $table->dropColumn('targets');
        });
    }
};
