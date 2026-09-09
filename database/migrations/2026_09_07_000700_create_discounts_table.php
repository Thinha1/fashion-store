<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code')->nullable()->unique();
            $table->string('scope')->index();
            $table->string('discount_type');
            $table->decimal('discount_value', 15, 2)->unsigned();
            $table->decimal('max_discount_amount', 15, 2)->unsigned()->nullable();
            $table->decimal('min_order_amount', 15, 2)->unsigned()->nullable();
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('used_count')->default(0);
            $table->unsignedInteger('usage_limit_per_customer')->nullable();
            $table->timestamp('starts_at')->index();
            $table->timestamp('ends_at')->index();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discounts');
    }
};
