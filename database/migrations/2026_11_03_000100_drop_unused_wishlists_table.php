<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The wishlists table was created with the schema but no screen, route or
     * job ever used it. Dropped until a wishlist feature is actually built.
     */
    public function up(): void
    {
        Schema::dropIfExists('wishlists');
    }

    public function down(): void
    {
        Schema::create('wishlists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['user_id', 'product_id']);
        });
    }
};
