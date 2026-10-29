<?php

namespace Tests\Feature;

use App\Mcp\McpActor;
use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\Discount;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\McpToken;
use App\Models\Order;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Staff\StaffAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CallsMcpTools;
use Tests\TestCase;

/**
 * Changing data from Claude: a write token proposes (nothing changes), the
 * person agrees, and apply_proposal does it as the role's system account.
 */
class McpWriteToolsTest extends TestCase
{
    use CallsMcpTools;
    use RefreshDatabase;

    private const PROPOSERS = ['propose_bulk_variant_discount', 'propose_confirm_orders', 'propose_create_vouchers', 'propose_draft_goods_receipts'];

    private function propose(string $token, string $tool, array $arguments = []): array
    {
        $result = $this->callTool($token, $tool, $arguments);
        $this->assertFalse($result['isError'], json_encode($result['content'] ?? [], JSON_UNESCAPED_UNICODE));

        return $result['structuredContent'];
    }

    private function apply(string $token, string $proposalId): array
    {
        return $this->callTool($token, 'apply_proposal', ['proposal_id' => $proposalId]);
    }

    private function pending(string $number, string $method, string $payment, int $minutesAgo): Order
    {
        return Order::factory()->create([
            'order_number' => $number, 'status' => 'pending', 'payment_method' => $method,
            'payment_status' => $payment, 'placed_at' => now()->subMinutes($minutesAgo),
        ]);
    }

    public function test_a_read_only_token_never_sees_the_tools_that_change_data(): void
    {
        $token = $this->tokenFor(['orders.manage', 'inventory.manage', 'discounts.manage', 'dashboard.view']);

        $names = $this->toolNames($token);
        foreach ([...self::PROPOSERS, 'apply_proposal'] as $tool) {
            $this->assertNotContains($tool, $names);
        }

        $call = $this->rpc($token, 'tools/call', ['name' => 'propose_confirm_orders', 'arguments' => (object) []]);
        $this->assertStringContainsString('not found', (string) $call->json('error.message'));
        $call = $this->rpc($token, 'tools/call', ['name' => 'apply_proposal', 'arguments' => (object) ['proposal_id' => str_repeat('a', 36)]]);
        $this->assertStringContainsString('not found', (string) $call->json('error.message'));
    }

    public function test_a_write_token_sees_the_proposing_tools_its_role_may_use(): void
    {
        $this->assertSame(
            ['apply_proposal', 'get_order', 'orders_to_process', 'propose_confirm_orders'],
            $this->toolNames($this->tokenFor(['orders.manage'], write: true)),
        );
        $this->assertSame(
            ['apply_proposal', 'find_stock', 'low_stock', 'propose_draft_goods_receipts'],
            $this->toolNames($this->tokenFor(['inventory.manage'], write: true)),
        );
        $this->assertSame(
            ['apply_proposal', 'propose_bulk_variant_discount', 'propose_create_vouchers'],
            $this->toolNames($this->tokenFor(['discounts.manage'], write: true)),
        );
        $this->assertSame(['sales_summary'], array_values(array_intersect(['sales_summary', 'apply_proposal'], $this->toolNames($this->tokenFor(['dashboard.view'], write: true)))));
    }

    public function test_the_proposing_tools_are_marked_read_only_and_apply_is_not(): void
    {
        $tools = collect($this->rpc($this->tokenFor(['orders.manage', 'inventory.manage', 'discounts.manage'], write: true), 'tools/list')->json('result.tools'))->keyBy('name');

        foreach (self::PROPOSERS as $tool) {
            $this->assertTrue($tools[$tool]['annotations']['readOnlyHint'] ?? false, $tool);
        }
        $this->assertSame(['readOnlyHint' => false, 'openWorldHint' => false], $tools['apply_proposal']['annotations']);
    }

    public function test_confirming_orders_is_previewed_first_and_done_once_as_the_roles_system_account(): void
    {
        $cod = $this->pending('DH-COD', 'cod', 'unpaid', 60);
        $paid = $this->pending('DH-PAID', 'bank_transfer', 'paid', 30);
        $unpaid = $this->pending('DH-WAIT', 'bank_transfer', 'unpaid', 10);
        $role = $this->roleWith(['orders.manage']);
        $token = McpToken::issue($role, 30, 'Claude Desktop', true)[1];

        $proposal = $this->propose($token, 'propose_confirm_orders');

        $this->assertSame('Xác nhận 2 đơn hàng', $proposal['title']);
        $this->assertSame(['Bỏ qua 1 đơn chuyển khoản chưa nhận tiền.'], $proposal['warnings']);
        $this->assertCount(2, $proposal['lines']);
        $this->assertSame('pending', $cod->fresh()->status, 'a proposal changes nothing');
        $this->assertSame(30, $proposal['expires_in_minutes']);

        $result = $this->apply($token, $proposal['proposal_id']);
        $this->assertFalse($result['isError']);
        $this->assertSame('Đã xác nhận 2 đơn hàng.', $result['structuredContent']['message']);

        $this->assertSame(['confirmed', 'confirmed', 'pending'], [$cod->fresh()->status, $paid->fresh()->status, $unpaid->fresh()->status]);
        $actor = User::query()->where('email', 'mcp-'.$role->code.'@fashion-store.invalid')->sole();
        $this->assertSame([$actor->id, $actor->id], [collect($cod->fresh()->status_history)->last()['actor_id'], $cod->fresh()->updated_by]);
        $this->assertSame('Claude (MCP) · '.$role->name, $actor->name);

        $logs = AuditLog::query()->where('action', 'ai.action')->get();
        $this->assertSame(1, $logs->count());
        $this->assertSame([$actor->id, 'mcp', 'Claude Desktop'], [$logs[0]->actor_id, $logs[0]->new_values['via'], $logs[0]->new_values['token']]);
        $this->assertSame(['propose_confirm_orders', 'apply_proposal'], AuditLog::query()->where('action', 'mcp.tool_called')->orderBy('id')->get()->map(fn ($log) => $log->new_values['tool'])->all());

        $again = $this->apply($token, $proposal['proposal_id']);
        $this->assertTrue($again['isError'], 'a proposal runs once');
    }

    public function test_a_proposal_with_nothing_to_do_is_a_tool_error_that_stores_nothing(): void
    {
        $token = $this->tokenFor(['orders.manage'], write: true);

        $result = $this->callTool($token, 'propose_confirm_orders');

        $this->assertTrue($result['isError']);
        $this->assertSame('Không có đơn nào đang chờ xác nhận khớp yêu cầu.', $result['content'][0]['text']);
    }

    public function test_draft_receipts_follow_each_variants_last_supplier_and_change_no_stock(): void
    {
        $supplier = Supplier::factory()->create(['name' => 'Coolmate Supply']);
        $variant = ProductVariant::factory()->for(Product::factory()->create(['status' => 'active']))
            ->create(['is_active' => true, 'stock_quantity' => 1, 'low_stock_threshold' => 5]);
        $receipt = GoodsReceipt::factory()->confirmed()->create(['supplier_id' => $supplier->id]);
        GoodsReceiptItem::query()->create(['goods_receipt_id' => $receipt->id, 'product_variant_id' => $variant->id, 'quantity' => 10, 'cost_price' => 90_000, 'subtotal' => 900_000]);
        $role = $this->roleWith(['inventory.manage']);
        $token = McpToken::issue($role, 30, null, true)[1];

        $proposal = $this->propose($token, 'propose_draft_goods_receipts');

        $this->assertSame('Soạn 1 phiếu nhập nháp cho 1 biến thể sắp hết', $proposal['title']);
        $this->assertSame(1, GoodsReceipt::query()->count(), 'a proposal changes nothing');

        $this->assertFalse($this->apply($token, $proposal['proposal_id'])['isError']);

        $draft = GoodsReceipt::query()->where('status', 'draft')->with('items')->sole();
        $this->assertSame($supplier->id, $draft->supplier_id);
        $this->assertSame(9, $draft->items->sole()->quantity);
        $this->assertSame(User::query()->where('status', User::STATUS_SYSTEM)->sole()->id, $draft->created_by);
        $this->assertSame(1, $variant->fresh()->stock_quantity);
    }

    public function test_vouchers_go_through_the_discount_forms_rules(): void
    {
        $token = $this->tokenFor(['discounts.manage'], write: true);
        $voucher = [
            'scope' => 'shipping', 'name' => 'Freeship đơn từ 500k', 'is_auto_apply' => true, 'discount_type' => 'percent',
            'discount_value' => 100, 'min_order_amount' => 500_000, 'starts_at' => '2030-11-01 00:00', 'ends_at' => '2030-11-03',
        ];

        $missingEnd = $this->callTool($token, 'propose_create_vouchers', ['vouchers' => [array_diff_key($voucher, ['ends_at' => 1])]]);
        $this->assertTrue($missingEnd['isError']);
        $this->assertStringContainsString('ngày kết thúc', $missingEnd['content'][0]['text']);

        $proposal = $this->propose($token, 'propose_create_vouchers', ['vouchers' => [$voucher]]);
        $this->assertSame('Tạo voucher', $proposal['title']);
        $this->assertStringContainsString('tự động áp dụng', $proposal['lines'][0]);
        $this->assertSame(0, Discount::query()->count());

        $this->assertFalse($this->apply($token, $proposal['proposal_id'])['isError']);

        $created = Discount::query()->sole();
        $this->assertSame(['shipping', true, '500000.00'], [$created->scope, $created->is_auto_apply, $created->min_order_amount]);
    }

    public function test_bulk_variant_discount_reaches_only_the_matching_variants(): void
    {
        $levents = Brand::factory()->create(['name' => 'Levents']);
        $matching = ProductVariant::factory()->for(Product::factory()->create(['brand_id' => $levents->id, 'status' => 'active']))->count(2)
            ->sequence(['size' => 'M'], ['size' => 'L'])->create(['is_active' => true]);
        $other = ProductVariant::factory()->for(Product::factory()->create(['status' => 'active']))->create(['is_active' => true]);
        $token = $this->tokenFor(['discounts.manage'], write: true);

        $proposal = $this->propose($token, 'propose_bulk_variant_discount', ['brand' => 'levents', 'discount_type' => 'percent', 'discount_value' => 20, 'ends_at' => '2030-11-30']);
        $this->assertSame('Giảm 20% cho 2 biến thể của 1 sản phẩm', $proposal['title']);
        $this->assertSame(0, Discount::query()->count());

        $this->assertFalse($this->apply($token, $proposal['proposal_id'])['isError']);

        $this->assertEqualsCanonicalizing($matching->modelKeys(), Discount::query()->pluck('product_variant_id')->all());
        $this->assertDatabaseMissing('discounts', ['product_variant_id' => $other->id]);

        $none = $this->callTool($token, 'propose_bulk_variant_discount', ['discount_type' => 'percent', 'discount_value' => 20, 'ends_at' => '2030-11-30']);
        $this->assertSame('Hãy cho biết giảm giá cho thương hiệu, danh mục hoặc sản phẩm nào.', $none['content'][0]['text']);
    }

    public function test_a_proposal_belongs_to_the_role_that_asked_for_it(): void
    {
        $order = $this->pending('DH-COD', 'cod', 'unpaid', 20);
        $mine = $this->tokenFor(['orders.manage'], write: true);
        $theirs = $this->tokenFor(['orders.manage'], write: true);
        $proposal = $this->propose($mine, 'propose_confirm_orders');

        $stolen = $this->apply($theirs, $proposal['proposal_id']);

        $this->assertTrue($stolen['isError']);
        $this->assertSame('pending', $order->fresh()->status);
        $this->assertTrue($this->apply($mine, 'khong-phai-de-xuat-nao')['isError']);
    }

    public function test_permissions_are_checked_again_when_the_proposal_is_applied(): void
    {
        $order = $this->pending('DH-COD', 'cod', 'unpaid', 20);
        $role = $this->roleWith(['orders.manage', 'discounts.manage']);
        $token = McpToken::issue($role, 30, null, true)[1];
        $proposal = $this->propose($token, 'propose_confirm_orders');

        $role->permissions()->detach(Permission::query()->where('code', 'orders.manage')->pluck('id'));
        $result = $this->apply($token, $proposal['proposal_id']);

        $this->assertTrue($result['isError']);
        $this->assertStringContainsString('không có quyền', $result['content'][0]['text']);
        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_the_system_account_is_not_staff_and_does_not_keep_the_last_permission_manager_alive(): void
    {
        $role = $this->roleWith(['roles.manage', 'staff.manage', 'orders.manage', 'admin.access']);
        $owner = User::factory()->create(['role_id' => $role->id]);
        $token = McpToken::issue($role, 30, null, true)[0]->load('role.permissions');
        $actor = McpActor::for($token);

        $this->assertSame(User::STATUS_SYSTEM, $actor->status);
        $this->assertSame($actor->id, McpActor::for($token)->id, 'one account per role');
        $this->assertTrue($actor->hasPermission('orders.manage'));

        // The system account shares the owner's role but is not a second permission manager.
        $someone = User::factory()->create(['role_id' => $this->roleWith(['staff.manage'])->id]);
        $access = app(StaffAccess::class);
        $this->assertSame('Phải còn ít nhất một tài khoản đang hoạt động có quyền phân quyền.', $access->checkStaffChange($someone, $owner, $role, 'locked'));

        $mcpOnly = $this->roleWith(['orders.manage']);
        McpActor::for(McpToken::issue($mcpOnly, 30, null, true)[0]->load('role.permissions'));
        $this->assertStringContainsString('tài khoản hệ thống', (string) $access->checkRoleDeletion($mcpOnly));

        $this->actingAs(User::factory()->admin()->create())->get(route('admin.staff.index'))
            ->assertOk()->assertSee($owner->name)->assertDontSee('Claude (MCP)');
        $this->get(route('admin.staff.edit', $actor))->assertNotFound();
    }

    public function test_the_command_issues_write_tokens_only_for_roles_that_can_write(): void
    {
        $role = $this->roleWith(['orders.manage']);
        $role->forceFill(['code' => 'van-hanh'])->save();
        $viewer = $this->roleWith(['dashboard.view']);
        $viewer->forceFill(['code' => 'xem'])->save();

        $this->artisan('mcp:token', ['role' => 'xem', '--write' => true])->assertFailed();
        $this->assertSame(0, McpToken::query()->count());

        $this->artisan('mcp:token', ['role' => 'xem'])->expectsOutputToContain('Token chỉ đọc.')->assertSuccessful();
        $this->assertFalse(McpToken::query()->sole()->can_write);

        $this->artisan('mcp:token', ['role' => 'van-hanh', '--write' => true])
            ->expectsOutputToContain('Token này CÓ quyền ghi')
            ->assertSuccessful();
        $written = McpToken::query()->where('role_id', $role->id)->sole();
        $this->assertTrue($written->can_write);
        $this->assertTrue(AuditLog::query()->where('action', 'mcp_token.created')->latest('id')->first()->new_values['can_write']);

        // Discounts can only be changed, never read, so such a role needs a write token.
        $this->roleWith(['discounts.manage'])->forceFill(['code' => 'giam-gia'])->save();
        $this->artisan('mcp:token', ['role' => 'giam-gia'])->assertFailed();
        $this->artisan('mcp:token', ['role' => 'giam-gia', '--write' => true])->expectsOutputToContain('Quyền dùng được qua MCP: discounts.manage.')->assertSuccessful();
    }
}
