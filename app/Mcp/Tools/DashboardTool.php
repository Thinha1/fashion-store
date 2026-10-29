<?php

namespace App\Mcp\Tools;

use App\Models\McpToken;
use App\Services\Audit\AuditLogger;
use App\Services\Reports\DashboardPeriod;
use App\Services\Reports\SalesDashboard;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

/**
 * A read-only MCP tool over the admin dashboard's numbers (SalesDashboard).
 * It is listed and callable only when the role behind the request's token
 * holds one of permissions(); every call is written to the audit log.
 * Subclasses carry #[IsReadOnly] themselves (attributes are not inherited).
 */
abstract class DashboardTool extends Tool
{
    /**
     * Permission codes, any one of which unlocks the tool.
     *
     * @return list<string>
     */
    abstract protected function permissions(): array;

    /**
     * The tool's answer, as plain data.
     *
     * @return array<string, mixed>
     */
    abstract protected function data(Request $request, SalesDashboard $dashboard): array;

    public function shouldRegister(): bool
    {
        return McpToken::current()?->allowsAny($this->permissions()) ?? false;
    }

    public function handle(Request $request, SalesDashboard $dashboard, AuditLogger $audit): ResponseFactory
    {
        $data = $this->data($request, $dashboard);

        if (($token = McpToken::current()) !== null) {
            $audit->record('mcp.tool_called', $token, new: [
                'role' => $token->role?->code,
                'token' => $token->name,
                'tool' => $this->name(),
                'arguments' => $request->all(),
            ]);
        }

        return Response::structured($data);
    }

    /** The `period` argument every period-based tool takes. */
    protected function periodSchema(JsonSchema $schema): Type
    {
        return $schema->string()
            ->enum(array_keys(DashboardPeriod::PRESETS))
            ->description('Khoảng thời gian, giống bộ lọc trên Dashboard: hom-nay, 7-ngay, 30-ngay (mặc định), 90-ngay, tat-ca (toàn thời gian).');
    }

    /** A `limit` argument from 1 to `$max`. */
    protected function limitSchema(JsonSchema $schema, int $default, int $max): Type
    {
        return $schema->integer()->min(1)->max($max)->description("Số dòng tối đa, từ 1 đến {$max} (mặc định {$default}).");
    }

    protected function period(Request $request): DashboardPeriod
    {
        $request->validate(['period' => ['nullable', 'string', Rule::in(array_keys(DashboardPeriod::PRESETS))]]);

        return DashboardPeriod::fromKey($request->get('period'));
    }

    protected function limit(Request $request, int $default, int $max): int
    {
        $request->validate(['limit' => ['nullable', 'integer', 'min:1', "max:{$max}"]]);

        return (int) ($request->get('limit') ?? $default);
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
