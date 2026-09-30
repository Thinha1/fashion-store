<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The receipt number (PN-0001) is derived from the auto-increment id, so it can only be written after the
     * row exists (see GoodsReceipt::booted()). The unique index stays; only the insert-time NOT NULL goes.
     */
    public function up(): void
    {
        Schema::table('goods_receipts', function (Blueprint $table) {
            $table->string('receipt_number')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('goods_receipts', function (Blueprint $table) {
            $table->string('receipt_number')->nullable(false)->change();
        });
    }
};
