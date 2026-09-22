<?php

namespace App\Http\Requests\Admin;

class UpdateAiSettingRequest extends BaseAdminRequest
{
    public function permissionCode(): string
    {
        return 'settings.manage';
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'endpoint' => ['nullable', 'string', 'max:255', 'url'],
            'model' => ['nullable', 'string', 'max:255'],
            'max_tokens' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            // Left blank = keep the currently saved key (see
            // AiSettingController::update) — it's never required, and an
            // empty string here never overwrites an existing key.
            'api_key' => ['nullable', 'string', 'max:1000'],
            'clear_api_key' => ['boolean'],
        ];
    }
}
