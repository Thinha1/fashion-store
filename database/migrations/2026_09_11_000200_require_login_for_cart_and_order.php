<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Đặt hàng bắt buộc đăng nhập: bỏ hẳn giỏ hàng/đơn hàng của khách vãng
     * lai để tránh phải xác thực bằng token lộ trên URL. Xem quyết định ở
     * fashion-store-plan.md §"Quy tắc nghiệp vụ".
     */
    public function up(): void
    {
        // Không còn hỗ trợ giỏ hàng khách vãng lai: dọn các bản ghi mồ côi
        // (nếu có) trước khi bắt buộc user_id.
        DB::table('carts')->whereNull('user_id')->delete();

        Schema::table('carts', function (Blueprint $table) {
            $table->dropUnique(['guest_token_hash']);
            $table->dropColumn('guest_token_hash');
        });

        Schema::table('carts', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropUnique(['guest_access_token_hash']);
            $table->dropColumn('guest_access_token_hash');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
            // Đơn luôn thuộc về một tài khoản đã đăng nhập; không cho xóa
            // user còn lịch sử đơn hàng (thay vì set null như trước).
            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->string('guest_access_token_hash', 64)->nullable()->unique()->after('user_id');
        });

        Schema::table('carts', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->string('guest_token_hash', 64)->nullable()->unique()->after('user_id');
        });
    }
};
