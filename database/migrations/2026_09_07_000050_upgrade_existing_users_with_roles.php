<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('roles')) {
            Schema::create('roles', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('code')->unique();
                $table->text('description')->nullable();
                $table->json('permissions')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasColumn('users', 'role_id')) {
            $customerRoleId = DB::table('roles')->insertGetId([
                'name' => 'Khách hàng',
                'code' => 'customer',
                'description' => 'Tài khoản mua hàng',
                'permissions' => json_encode([], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            Schema::table('users', function (Blueprint $table) {
                $table->foreignId('role_id')->nullable()->after('id')->constrained()->restrictOnDelete();
            });

            DB::table('users')->whereNull('role_id')->update(['role_id' => $customerRoleId]);
            DB::statement('ALTER TABLE users MODIFY role_id BIGINT UNSIGNED NOT NULL');
        }

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'phone')) {
                $table->string('phone', 20)->nullable()->unique()->after('email');
            }

            if (! Schema::hasColumn('users', 'status')) {
                $table->string('status')->default('active')->index()->after('phone');
            }
        });
    }

    public function down(): void
    {
        // Compatibility migration: columns may belong to the base users migration.
    }
};
