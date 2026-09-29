<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * One saved configuration of the self-hosted AI backend both chat agents call
 * (see App\Services\Ai\OpenAiCompatibleProvider). Several can exist; the one
 * flagged `is_primary` is the one in use. Read through
 * App\Services\Ai\AiSettings, never directly — that service is what applies
 * the "empty means fall back to .env" rule per field.
 */
#[Fillable(['name', 'endpoint', 'api_key', 'model', 'max_tokens', 'is_primary', 'updated_by'])]
class AiSetting extends Model
{
    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'max_tokens' => 'integer',
            'is_primary' => 'boolean',
        ];
    }

    /**
     * Only ever the last four characters — safe to render in the admin UI.
     */
    public function maskedApiKey(): ?string
    {
        $apiKey = $this->api_key;
        if (! $apiKey) {
            return null;
        }

        return Str::length($apiKey) <= 4 ? str_repeat('•', 8) : str_repeat('•', 8).Str::substr($apiKey, -4);
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
