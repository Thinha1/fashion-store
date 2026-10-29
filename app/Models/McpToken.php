<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A bearer token for the MCP server, issued per role (see
 * `php artisan mcp:token`). Only the hash is stored.
 */
#[Fillable(['role_id', 'name', 'can_write', 'token_hash', 'expires_at', 'last_used_at', 'revoked_at'])]
class McpToken extends Model
{
    /** Container key of the token behind the current MCP request. */
    public const CURRENT = 'mcp.token';

    private const PREFIX = 'fsm_';

    /**
     * Creates a token for `$role` and returns it with the plain text, which
     * is never stored and cannot be shown again. A token is read-only unless
     * `$canWrite`.
     *
     * @return array{0: self, 1: string}
     */
    public static function issue(Role $role, int $days, ?string $name = null, bool $canWrite = false): array
    {
        $plain = self::PREFIX.Str::random(48);

        $token = self::query()->create([
            'role_id' => $role->id,
            'name' => $name ?: 'MCP '.$role->name,
            'can_write' => $canWrite,
            'token_hash' => self::hash($plain),
            'expires_at' => now()->addDays($days),
        ]);

        return [$token, $plain];
    }

    /**
     * The usable token matching a bearer string, with its role's permissions
     * loaded; null when unknown, expired or revoked.
     */
    public static function findUsable(?string $plain): ?self
    {
        if ($plain === null || ! str_starts_with($plain, self::PREFIX)) {
            return null;
        }

        return self::query()->usable()->where('token_hash', self::hash($plain))->with('role.permissions')->first();
    }

    /** The token the current MCP request was made with, if any. */
    public static function current(): ?self
    {
        return app()->bound(self::CURRENT) ? app(self::CURRENT) : null;
    }

    public static function hash(string $plain): string
    {
        return hash('sha256', $plain);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeUsable(Builder $query): void
    {
        $query->whereNull('revoked_at')->where('expires_at', '>', now());
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /** Whether the role behind this token holds any of `$codes`, as of now. */
    public function allowsAny(array $codes): bool
    {
        return $this->role !== null && collect($codes)->contains(fn (string $code): bool => $this->role->hasPermission($code));
    }

    /**
     * Whether this token may change data: it was issued with --write and its
     * role holds one of `$codes`, as of now.
     */
    public function allowsWrite(array $codes): bool
    {
        return $this->can_write && $this->allowsAny($codes);
    }

    protected function casts(): array
    {
        return [
            'can_write' => 'boolean',
            'expires_at' => 'datetime',
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }
}
