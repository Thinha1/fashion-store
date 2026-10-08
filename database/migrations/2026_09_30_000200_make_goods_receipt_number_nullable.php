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

    /**
     * Rolling back needs every receipt to have a number; the created hook always sets one, so that only fails
     * if rows were inserted around the model (e.g. raw SQL) — fix those first.
     */
    public function down(): void
    {
        Schema::table('goods_receipts', function (Blueprint $table) {
            $table->string('receipt_number')->nullable(false)->change();
        });
    }
};
