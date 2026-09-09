<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('guest_access_token_hash', 64)->nullable()->unique();
            $table->foreignId('discount_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('pending')->index();
            $table->json('status_history');
            $table->string('payment_method');
            $table->string('payment_status')->default('unpaid')->index();
            $table->string('transaction_code')->nullable();
            $table->string('payment_proof_path')->nullable();
            $table->timestamp('payment_proof_submitted_at')->nullable();
            $table->foreignId('payment_reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('payment_reviewed_at')->nullable();
            $table->text('payment_rejection_reason')->nullable();
            $table->string('customer_name');
            $table->string('customer_email');
            $table->string('customer_phone', 20);
            $table->string('province_name');
            $table->string('district_name');
            $table->string('ward_name');
            $table->text('shipping_address');
            $table->text('customer_note')->nullable();
            $table->decimal('subtotal', 15, 2)->unsigned();
            $table->decimal('discount_amount', 15, 2)->unsigned()->default(0);
            $table->decimal('shipping_fee', 15, 2)->unsigned()->default(0);
            $table->decimal('grand_total', 15, 2)->unsigned();
            $table->timestamp('placed_at')->index();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
