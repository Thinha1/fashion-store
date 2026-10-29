<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vouchers (discounts with scope=order or scope=shipping) get a display name
 * and an auto-apply flag; an order keeps the shipping voucher it used and how
 * much it took off the shipping fee, separately from the order voucher.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('discounts', function (Blueprint $table) {
            $table->string('name', 120)->nullable()->after('code');
            $table->boolean('is_auto_apply')->default(false)->after('is_active');
            $table->index(['scope', 'is_auto_apply', 'is_active']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('shipping_discount_id')->nullable()->after('discount_id')->constrained('discounts')->nullOnDelete();
            $table->decimal('shipping_discount_amount', 15, 2)->unsigned()->default(0)->after('shipping_fee');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('shipping_discount_id');
            $table->dropColumn('shipping_discount_amount');
        });

        Schema::table('discounts', function (Blueprint $table) {
            $table->dropIndex(['scope', 'is_auto_apply', 'is_active']);
            $table->dropColumn(['name', 'is_auto_apply']);
        });
    }
};
