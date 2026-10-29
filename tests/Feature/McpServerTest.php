<?php

namespace Tests\Feature;

use App\Mcp\Tools\LatestOrdersTool;
use App\Models\AuditLog;
use App\Models\McpToken;
use App\Models\Order;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Services\Reports\DashboardPeriod;
use App\Services\Reports\SalesDashboard;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class McpServerTest extends TestCase
{
    use RefreshDatabase;

    private const ALL_TOOLS = [
        'sales_summary', 'sales_series', 'top_products', 'order_status_breakdown',
        'attention_counts', 'low_stock', 'latest_orders', 'find_stock',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-10-10 15:30:00'));
    }

    /** A token for a fresh role holding exactly `$codes`. */
    private function tokenFor(array $codes): string
    {
        $role = Role::factory()->create();
        $role->permissions()->attach(Permission::query()->whereIn('code', $codes)->pluck('id'));

        return McpToken::issue($role, 30)[1];
    }

    private function rpc(?string $token, string $method, array $params = []): TestResponse
    {
        return $this->withHeaders(array_filter([
            'Authorization' => $token === null ? null : 'Bearer '.$token,
            'Accept' => 'application/json, text/event-stream',
        ]))->postJson('/ai/shop', ['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => $params ?: (object) []]);
    }

    private function callTool(string $token, string $tool, array $arguments = []): array
    {
        $response = $this->rpc($token, 'tools/call', ['name' => $tool, 'arguments' => (object) $arguments])->assertOk();

        return $response->json('result');
    }

    /** @return list<string> */
    private function toolNames(string $token): array
    {
        return collect($this->rpc($token, 'tools/list')->assertOk()->json('result.tools'))->pluck('name')->sort()->values()->all();
    }

    public function test_requests_without_a_usable_token_are_rejected(): void
    {
        $role = Role::factory()->create();
        $role->permissions()->attach(Permission::query()->where('code', 'dashboard.view')->pluck('id'));
        [$expired, $expiredPlain] = McpToken::issue($role, 1);
        $expired->forceFill(['expires_at' => now()->subMinute()])->save();
        [$revoked, $revokedPlain] = McpToken::issue($role, 30);
        $revoked->forceFill(['revoked_at' => now()])->save();

        foreach ([null, 'fsm_khong-dung', $expiredPlain, $revokedPlain] as $token) {
            $response = $this->rpc($token, 'tools/list')->assertUnauthorized();
            $this->assertStringStartsWith('Bearer', (string) $response->headers->get('WWW-Authenticate'));
        }

        $this->assertNull(McpToken::query()->find($expired->id)->last_used_at);
    }

    public function test_a_role_sees_only_the_tools_its_permissions_unlock(): void
    {
        $this->assertSame(collect(self::ALL_TOOLS)->sort()->values()->all(), $this->toolNames($this->tokenFor(['dashboard.view'])));
        $this->assertSame(['find_stock', 'low_stock'], $this->toolNames($this->tokenFor(['inventory.manage'])));
        $this->assertSame(['get_order', 'latest_orders', 'orders_to_process'], $this->toolNames($this->tokenFor(['orders.view'])));
        $this->assertSame([], $this->toolNames($this->tokenFor(['catalog.manage'])));
    }

    public function test_tools_are_marked_read_only(): void
    {
        $tools = $this->rpc($this->tokenFor(['dashboard.view']), 'tools/list')->json('result.tools');

        foreach ($tools as $tool) {
            $this->assertTrue($tool['annotations']['readOnlyHint'] ?? false, $tool['name'].' is not read-only');
        }
    }

    public function test_a_tool_outside_the_roles_permissions_cannot_be_called(): void
    {
        $response = $this->rpc($this->tokenFor(['inventory.manage']), 'tools/call', ['name' => 'sales_summary', 'arguments' => (object) []]);

        $this->assertStringContainsString('not found', (string) $response->json('error.message'));
        $this->assertSame(0, AuditLog::query()->where('action', 'mcp.tool_called')->count());
    }

    public function test_permissions_are_read_at_call_time(): void
    {
        $role = Role::factory()->create();
        $role->permissions()->attach(Permission::query()->where('code', 'dashboard.view')->pluck('id'));
        $token = McpToken::issue($role, 30)[1];
        $this->assertContains('sales_summary', $this->toolNames($token));

        $role->permissions()->detach();

        $this->assertSame([], $this->toolNames($token));
    }

    public function test_sales_summary_matches_the_dashboard(): void
    {
        Order::factory()->create(['placed_at' => now()->subDays(2), 'grand_total' => 500_000, 'status' => 'delivered', 'payment_status' => 'paid']);
        Order::factory()->create(['placed_at' => now()->subDays(3), 'grand_total' => 300_000, 'status' => 'pending', 'payment_status' => 'unpaid']);
        Order::factory()->create(['placed_at' => now()->subDays(4), 'grand_total' => 900_000, 'status' => 'cancelled']);
        Order::factory()->create(['placed_at' => now()->subDays(10), 'grand_total' => 400_000, 'status' => 'delivered', 'payment_status' => 'paid']);

        $result = $this->callTool($this->tokenFor(['dashboard.view']), 'sales_summary', ['period' => '7-ngay']);
        $data = $result['structuredContent'];
        $totals = app(SalesDashboard::class)->totals(DashboardPeriod::fromKey('7-ngay'));

        $this->assertFalse($result['isError']);
        $this->assertSame('7-ngay', $data['period']['key']);
        $this->assertSame(500_000, $data['revenue']);
        $this->assertSame($totals['sales'], $data['sales']);
        $this->assertSame(800_000, $data['sales']);
        $this->assertSame(3, $data['orders']);
        $this->assertSame(1, $data['cancelled_orders']);
        $this->assertEquals(25, $data['change_percent']['revenue']);
        $this->assertSame('so với 7 ngày trước', $data['change_percent']['compared_with']);
    }

    public function test_an_unknown_period_is_a_tool_error(): void
    {
        $result = $this->callTool($this->tokenFor(['dashboard.view']), 'sales_summary', ['period' => 'thang-9']);

        $this->assertTrue($result['isError']);
    }

    public function test_limits_are_capped(): void
    {
        $token = $this->tokenFor(['dashboard.view']);

        $this->assertTrue($this->callTool($token, 'latest_orders', ['limit' => 21])['isError']);
        $this->assertTrue($this->callTool($token, 'low_stock', ['limit' => 0])['isError']);
        $this->assertFalse($this->callTool($token, 'low_stock', ['limit' => 50])['isError']);
    }

    public function test_series_status_breakdown_and_top_products_follow_the_dashboard(): void
    {
        $order = Order::factory()->create(['placed_at' => now()->subHour(), 'grand_total' => 200_000, 'status' => 'pending']);
        $order->items()->create([
            'product_name' => 'Áo thun', 'sku' => 'AT-M', 'size_name' => 'M', 'color_name' => 'Đen',
            'original_unit_price' => 100_000, 'unit_price' => 100_000, 'quantity' => 2, 'line_total' => 200_000,
        ]);
        $token = $this->tokenFor(['dashboard.view']);

        $series = $this->callTool($token, 'sales_series', ['period' => 'hom-nay'])['structuredContent'];
        $this->assertSame('hour', $series['granularity']);
        $this->assertSame(200_000, array_sum(array_column($series['points'], 'sales')));

        $statuses = collect($this->callTool($token, 'order_status_breakdown')['structuredContent']['statuses'])->keyBy('status');
        $this->assertSame(1, $statuses['pending']['count']);
        $this->assertFalse($statuses['cancelled']['in_pipeline']);

        $top = $this->callTool($token, 'top_products')['structuredContent']['products'];
        $this->assertSame([['name' => 'Áo thun', 'quantity' => 2, 'sales' => 200_000]], $top);
    }

    public function test_low_stock_and_attention_counts(): void
    {
        $product = Product::factory()->create(['status' => 'active', 'name' => 'Quần jean']);
        ProductVariant::factory()->for($product)->create(['sku' => 'QJ-30', 'stock_quantity' => 0, 'low_stock_threshold' => 5, 'is_active' => true]);
        ProductVariant::factory()->for($product)->create(['sku' => 'QJ-32', 'stock_quantity' => 3, 'low_stock_threshold' => 5, 'is_active' => true]);
        ProductVariant::factory()->for($product)->create(['sku' => 'QJ-34', 'stock_quantity' => 40, 'low_stock_threshold' => 5, 'is_active' => true]);
        Order::factory()->create(['status' => 'pending']);

        $lowStock = $this->callTool($this->tokenFor(['inventory.manage']), 'low_stock', ['limit' => 1])['structuredContent'];
        $this->assertSame(2, $lowStock['total_low_stock']);
        $this->assertSame([['product' => 'Quần jean', 'sku' => 'QJ-30']], array_map(fn ($row) => array_intersect_key($row, ['product' => 1, 'sku' => 1]), $lowStock['variants']));

        $counts = $this->callTool($this->tokenFor(['dashboard.view']), 'attention_counts')['structuredContent'];
        $this->assertSame(['pending_orders' => 1, 'out_of_stock_variants' => 1, 'low_stock_variants' => 2], $counts);
    }

    public function test_latest_orders_hide_customer_details(): void
    {
        Order::factory()->create([
            'order_number' => 'FS-0001',
            'customer_name' => 'Nguyễn Văn An',
            'customer_email' => 'an@example.com',
            'customer_phone' => '0901234567',
            'shipping_address' => '12 Lê Lợi',
            'grand_total' => 450_000,
        ]);

        $result = $this->callTool($this->tokenFor(['orders.view']), 'latest_orders');
        $order = $result['structuredContent']['orders'][0];
        $raw = json_encode($result, JSON_UNESCAPED_UNICODE);

        $this->assertSame('FS-0001', $order['order_number']);
        $this->assertSame('N. V. An', $order['customer']);
        $this->assertSame(450_000, $order['grand_total']);
        foreach (['Nguyễn Văn An', 'an@example.com', '0901234567', '12 Lê Lợi'] as $secret) {
            $this->assertStringNotContainsString($secret, $raw);
        }
    }

    public function test_names_are_masked_to_initials_and_given_name(): void
    {
        $this->assertSame('N. V. An', LatestOrdersTool::maskName('Nguyễn Văn An'));
        $this->assertSame('Đ. T. M. Ánh', LatestOrdersTool::maskName('  đặng thị minh Ánh '));
        $this->assertSame('Linh', LatestOrdersTool::maskName('Linh'));
        $this->assertSame('Khách', LatestOrdersTool::maskName(''));
    }

    public function test_each_tool_call_is_audited_and_marks_the_token_used(): void
    {
        $token = $this->tokenFor(['dashboard.view']);

        $this->callTool($token, 'attention_counts');

        $log = AuditLog::query()->where('action', 'mcp.tool_called')->sole();
        $this->assertNull($log->actor_id);
        $this->assertSame('attention_counts', $log->new_values['tool']);
        $this->assertSame('MCP: gọi công cụ', $log->actionLabel());
        $this->assertNotNull(McpToken::query()->sole()->last_used_at);
    }

    public function test_each_token_is_limited_to_sixty_requests_a_minute(): void
    {
        $token = $this->tokenFor(['dashboard.view']);
        $other = $this->tokenFor(['dashboard.view']);

        for ($i = 0; $i < 60; $i++) {
            $this->rpc($token, 'ping')->assertOk();
        }

        $this->rpc($token, 'ping')->assertTooManyRequests();
        $this->rpc($other, 'ping')->assertOk();
    }

    public function test_the_command_issues_and_revokes_tokens_per_role(): void
    {
        $role = Role::factory()->create(['code' => 'kho']);
        $role->permissions()->attach(Permission::query()->where('code', 'inventory.manage')->pluck('id'));

        $this->artisan('mcp:token', ['role' => 'kho', '--days' => 7])
            ->expectsOutputToContain('Quyền dùng được qua MCP: inventory.manage.')
            ->expectsOutputToContain('Token chỉ đọc.')
            ->assertSuccessful();

        $token = McpToken::query()->sole();
        $this->assertFalse($token->can_write);
        $this->assertSame($role->id, $token->role_id);
        $this->assertSame('2026-10-17', $token->expires_at->toDateString());
        $this->assertSame(64, strlen($token->token_hash));
        $this->assertSame(1, AuditLog::query()->where('action', 'mcp_token.created')->count());

        $this->artisan('mcp:token', ['role' => 'kho', '--revoke' => true])
            ->expectsOutputToContain('Đã thu hồi 1 token')
            ->assertSuccessful();
        $this->assertNotNull($token->fresh()->revoked_at);
    }

    public function test_the_command_refuses_unknown_roles_and_roles_without_mcp_permissions(): void
    {
        Role::factory()->create(['code' => 'bien-tap'])
            ->permissions()->attach(Permission::query()->where('code', 'catalog.manage')->pluck('id'));

        $this->artisan('mcp:token', ['role' => 'khong-co'])->assertFailed();
        $this->artisan('mcp:token', ['role' => 'bien-tap'])->assertFailed();
        $this->artisan('mcp:token', ['role' => 'bien-tap', '--days' => 0])->assertFailed();
        $this->assertSame(0, McpToken::query()->count());
    }
}
