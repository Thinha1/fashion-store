<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The shop no longer takes transfer receipts: a bank transfer is matched
     * by the SePay webhook, or confirmed by staff from the order page. Drops
     * the receipt columns and the "payments.manage" permission that guarded
     * the review screen (its permission_role rows cascade).
     * payment_reviewed_by / payment_reviewed_at stay: they record who
     * confirmed the money (null reviewer = SePay) and when.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn(['payment_proof_path', 'payment_proof_submitted_at', 'payment_rejection_reason']);
        });

        DB::table('permissions')->where('code', 'payments.manage')->delete();
    }

    /**
     * Brings the columns and the permission back empty — receipts and role
     * grants removed by up() are not restored.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->string('payment_proof_path')->nullable()->after('transaction_code');
            $table->timestamp('payment_proof_submitted_at')->nullable()->after('payment_proof_path');
            $table->text('payment_rejection_reason')->nullable()->after('payment_reviewed_at');
        });

        DB::table('permissions')->insert([
            'code' => 'payments.manage',
            'name' => 'Duyệt chứng từ chuyển khoản (fallback thủ công SePay)',
            'group' => 'orders',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
