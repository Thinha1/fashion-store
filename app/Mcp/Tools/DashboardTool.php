<?php

namespace App\Mcp\Tools;

use App\Services\Audit\AuditLogger;
use App\Services\Reports\DashboardPeriod;
use App\Services\Reports\SalesDashboard;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

/**
 * A read-only MCP tool over the admin dashboard's numbers (SalesDashboard).
 */
abstract class DashboardTool extends ShopTool
{
    /**
     * The tool's answer, as plain data.
     *
     * @return array<string, mixed>
     */
    abstract protected function data(Request $request, SalesDashboard $dashboard): array;

    public function handle(Request $request, SalesDashboard $dashboard, AuditLogger $audit): ResponseFactory
    {
        $data = $this->data($request, $dashboard);

        $this->audit($audit, $request);

        return Response::structured($data);
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
}
