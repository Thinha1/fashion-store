<?php

namespace App\Mcp\Tools;

use App\Services\Audit\AuditLogger;
use App\Services\Reports\SalesDashboard;
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
}
