<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The returns feature (/doi-tra) is dropped from the project: its table never
 * got a screen, and its permission granted nothing. Revoking the permission
 * also clears it from every role (permission_role cascades).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('return_requests');
        DB::table('permissions')->where('code', 'returns.manage')->delete();
    }

    /** Brings back the empty table and the permission; roles must be granted it again. */
    public function down(): void
    {
        Schema::create('return_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_number')->unique();
            $table->foreignId('order_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            $table->unsignedInteger('quantity');
            $table->text('reason');
            $table->json('image_paths')->nullable();
            $table->string('status')->default('pending')->index();
            $table->text('resolution')->nullable();
            $table->unsignedInteger('restock_quantity')->default(0);
            $table->text('admin_note')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });

        DB::table('permissions')->insertOrIgnore([
            'code' => 'returns.manage', 'name' => 'Duyệt yêu cầu đổi/trả', 'group' => 'orders',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
};
