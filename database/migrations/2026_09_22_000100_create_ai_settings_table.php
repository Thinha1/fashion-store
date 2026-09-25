<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Single-row table: which self-hosted AI backend both the admin and
     * customer chat agents call (see App\Services\Ai\OpenAiCompatibleProvider).
     * Every column is nullable — a null/empty value means "fall back to the
     * .env-backed config('services.ai.*') default" (see App\Services\Ai\AiSettings),
     * so an environment where nobody has opened the settings screen yet keeps
     * working exactly as before this table existed.
     */
    public function up(): void
    {
        Schema::create('ai_settings', function (Blueprint $table) {
            $table->id();
            $table->string('endpoint')->nullable();
            // Encrypted at rest (see AiSetting's `casts()`) — never stored as plaintext.
            $table->text('api_key')->nullable();
            $table->string('model')->nullable();
            $table->unsignedInteger('max_tokens')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_settings');
    }
};
