<?php

namespace App\Mcp\Tools;

use App\Models\McpToken;
use App\Services\Audit\AuditLogger;
use App\Services\Reports\DashboardPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Validation\Rule;
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

    /** The `period` argument every period-based tool takes. */
    protected function periodSchema(JsonSchema $schema): Type
    {
        return $schema->string()
            ->enum(array_keys(DashboardPeriod::PRESETS))
            ->description('Khoảng thời gian, giống bộ lọc trên Dashboard: hom-nay, 7-ngay, 30-ngay (mặc định), 90-ngay, tat-ca (toàn thời gian).');
    }

    protected function period(Request $request): DashboardPeriod
    {
        $request->validate(['period' => ['nullable', 'string', Rule::in(array_keys(DashboardPeriod::PRESETS))]]);

        return DashboardPeriod::fromKey($request->get('period'));
    }

    /**
     * @return array{key: string, label: string, from: ?string, to: string}
     */
    protected function describePeriod(DashboardPeriod $period): array
    {
        return [
            'key' => $period->key,
            'label' => DashboardPeriod::PRESETS[$period->key],
            'from' => $period->start ? $this->time($period->start) : null,
            'to' => $this->time($period->end),
        ];
    }

    protected function time(\DateTimeInterface $moment): string
    {
        return CarbonImmutable::instance($moment)->toIso8601String();
    }
}
