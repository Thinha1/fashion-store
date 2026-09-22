<?php

namespace App\Services\Ai;

use App\Models\AiSetting;

/**
 * Resolves which self-hosted AI backend to call: a field set by an admin via
 * /admin/cai-dat/ai (see Admin\AiSettingController) always wins, an empty
 * field falls back to the .env-backed config('services.ai.*') default — so
 * an environment where nobody has opened the settings screen yet (a fresh
 * `docker compose up`, CI) keeps working exactly as before this existed.
 *
 * Constructed either from the persisted row (the normal app path, see the
 * binding in AppServiceProvider) or from an in-memory, unsaved AiSetting
 * built from a settings form's current (not-yet-submitted) input — see
 * Admin\AiSettingController::test() — so "Kiểm tra kết nối" can verify
 * values before they're saved.
 */
class AiSettings
{
    public function __construct(private readonly ?AiSetting $record = null) {}

    public static function current(): self
    {
        return new self(AiSetting::query()->first());
    }

    public function endpoint(): string
    {
        return $this->record?->endpoint ?: (string) config('services.ai.openai_compatible.endpoint');
    }

    public function apiKey(): string
    {
        return $this->record?->api_key ?: (string) config('services.ai.openai_compatible.key');
    }

    public function model(): string
    {
        return $this->record?->model ?: (string) config('services.ai.openai_compatible.model');
    }

    public function maxTokens(): int
    {
        return $this->record?->max_tokens ?: (int) config('services.ai.max_tokens');
    }
}
