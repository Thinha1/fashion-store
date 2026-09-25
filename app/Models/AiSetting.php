<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Single-row settings for the self-hosted AI backend both chat agents call
 * (see App\Services\Ai\OpenAiCompatibleProvider). Read through
 * App\Services\Ai\AiSettings, never directly — that service is what applies
 * the "empty means fall back to .env" rule per field.
 */
#[Fillable(['endpoint', 'api_key', 'model', 'max_tokens', 'updated_by'])]
class AiSetting extends Model
{
    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'max_tokens' => 'integer',
        ];
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
