<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ai_settings used to hold exactly one row; it now holds any number of
     * saved backend configurations, and `is_primary` marks the single one
     * the chat agents actually call (see App\Services\Ai\AiSettings::current()).
     * MySQL has no partial unique index, so "at most one primary" is enforced
     * in code (AiSettingController::makePrimary), not by the schema.
     *
     * The pre-existing row (if any) is carried over as the primary one, so an
     * environment that already saved a config keeps calling the same backend.
     */
    public function up(): void
    {
        Schema::table('ai_settings', function (Blueprint $table) {
            $table->string('name')->nullable()->after('id');
            $table->boolean('is_primary')->default(false)->after('max_tokens')->index();
        });

        $firstId = DB::table('ai_settings')->orderBy('id')->value('id');
        if ($firstId !== null) {
            DB::table('ai_settings')->where('id', $firstId)->update(['name' => 'Mặc định', 'is_primary' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('ai_settings', function (Blueprint $table) {
            $table->dropIndex(['is_primary']);
            $table->dropColumn(['name', 'is_primary']);
        });
    }
};
