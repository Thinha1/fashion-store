<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Single-row table: the shop's receiving bank account (VietQR) and the
     * SePay webhook key, edited at /admin/cai-dat/thanh-toan. Every column is
     * nullable — an empty value falls back to the .env-backed config (see
     * App\Services\Payments\BankTransfer), so an environment where nobody has
     * opened the screen keeps working as before.
     */
    public function up(): void
    {
        Schema::create('payment_settings', function (Blueprint $table) {
            $table->id();
            $table->string('bank_id', 20)->nullable();
            $table->string('account_number', 30)->nullable();
            $table->string('account_name', 100)->nullable();
            // Encrypted at rest (see PaymentSetting's `casts()`).
            $table->text('sepay_webhook_api_key')->nullable();
            // The public webhook URL SePay calls (a domain or a Tailscale
            // Funnel URL) — APP_URL is often localhost in development.
            $table->string('webhook_url')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_settings');
    }
};
