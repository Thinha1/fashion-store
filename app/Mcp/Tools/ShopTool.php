<?php

namespace App\Mcp\Tools;

use App\Models\McpToken;
use App\Services\Audit\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Tool;

/**
 * What every tool of the shop's MCP server shares: it is listed and callable
 * only when the role behind the request's token holds one of permissions(),
 * and every call is written to the audit log. Subclasses carry #[IsReadOnly]
 * themselves (attributes are not inherited).
 */
abstract class ShopTool extends Tool
{
    /**
     * Permission codes, any one of which unlocks the tool.
     *
     * @return list<string>
     */
    abstract protected function permissions(): array;

    public function shouldRegister(): bool
    {
        return McpToken::current()?->allowsAny($this->permissions()) ?? false;
    }

    protected function audit(AuditLogger $audit, Request $request): void
    {
        if (($token = McpToken::current()) === null) {
            return;
        }

        $audit->record('mcp.tool_called', $token, new: [
            'role' => $token->role?->code,
            'token' => $token->name,
            'tool' => $this->name(),
            'arguments' => $request->all(),
        ]);
    }

    /** A `limit` argument from 1 to `$max`. */
    protected function limitSchema(JsonSchema $schema, int $default, int $max): Type
    {
        return $schema->integer()->min(1)->max($max)->description("Số dòng tối đa, từ 1 đến {$max} (mặc định {$default}).");
    }

    protected function limit(Request $request, int $default, int $max): int
    {
        $request->validate(['limit' => ['nullable', 'integer', 'min:1', "max:{$max}"]]);

        return (int) ($request->get('limit') ?? $default);
    }

    protected function time(\DateTimeInterface $moment): string
    {
        return CarbonImmutable::instance($moment)->toIso8601String();
    }
}
