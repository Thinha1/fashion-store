<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('discount_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_name');
            $table->string('sku');
            $table->string('size_name', 50);
            $table->string('color_name', 100);
            $table->decimal('original_unit_price', 15, 2)->unsigned();
            $table->decimal('discount_amount', 15, 2)->unsigned()->default(0);
            $table->decimal('unit_price', 15, 2)->unsigned();
            $table->unsignedInteger('quantity');
            $table->decimal('line_total', 15, 2)->unsigned();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
