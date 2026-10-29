<?php

namespace App\Services\Ai\Actions;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Previewed actions waiting for the staff member's click. The browser only
 * ever holds an opaque id: the parameters stay on the server, belong to the
 * staff member who asked, and are used at most once, so a double click or a
 * replayed request cannot run an action twice or with other parameters.
 */
class AgentActionProposals
{
    public const TTL_MINUTES = 30;

    /**
     * @param  array<string, mixed>  $params
     */
    public function store(User $actor, string $type, array $params): string
    {
        $id = (string) Str::uuid();
        Cache::put($this->key($id), ['user_id' => $actor->id, 'type' => $type, 'params' => $params], now()->addMinutes(self::TTL_MINUTES));

        return $id;
    }

    /**
     * The proposal, removed so it cannot be confirmed again; null when it is
     * unknown, expired, already used or someone else's.
     *
     * @return array{type: string, params: array<string, mixed>}|null
     */
    public function take(User $actor, string $id): ?array
    {
        $proposal = Cache::get($this->key($id));
        if (! is_array($proposal) || $proposal['user_id'] !== $actor->id) {
            return null;
        }

        return Cache::pull($this->key($id)) === null ? null : ['type' => $proposal['type'], 'params' => $proposal['params']];
    }

    private function key(string $id): string
    {
        return 'ai-action:'.$id;
    }
}
