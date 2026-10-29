<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Discount;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\Order;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Ai\ProductDraftResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * The assistant proposes admin actions; the server previews them, and only a
 * confirming click by a staff member with the right permission does them.
 */
class AgentActionsTest extends TestCase
{
    use RefreshDatabase;

    private function staffWith(string ...$codes): User
    {
        $role = Role::factory()->create();
        $role->permissions()->attach(Permission::query()->whereIn('code', $codes)->pluck('id'));

        return User::factory()->create(['role_id' => $role->id]);
    }

    /**
     * The cards the resolver makes from a reply proposing these actions.
     *
     * @param  list<array<string, mixed>>  $actions
     * @return list<array<string, mixed>>
     */
    private function cards(User $actor, array $actions): array
    {
        return app(ProductDraftResolver::class)->resolve(json_encode(['actions' => $actions]), $actor)['actions'];
    }

    private function confirm(User $actor, string $id): TestResponse
    {
        return $this->actingAs($actor)->postJson(route('admin.ai.actions.confirm', $id));
    }

    public function test_a_voucher_is_only_previewed_until_the_staff_member_confirms_it_once(): void
    {
        $admin = User::factory()->admin()->create();

        [$card] = $this->cards($admin, [['type' => 'create_vouchers', 'vouchers' => [[
            'scope' => 'shipping', 'name' => 'Freeship đơn từ 500k', 'code' => '', 'is_auto_apply' => true,
            'discount_type' => 'percent', 'discount_value' => 100, 'min_order_amount' => '500k',
            'starts_at' => '2030-11-01 00:00', 'ends_at' => '2030-11-03',
        ]]]]);

        $this->assertSame('Tạo voucher', $card['title']);
        $this->assertStringContainsString('Voucher giảm phí ship "Freeship đơn từ 500k" (tự động áp dụng): giảm 100%, đơn từ 500.000 ₫', $card['lines'][0]);
        $this->assertStringContainsString('đến 23:59 03/11/2030', $card['lines'][0]);
        $this->assertDatabaseCount('discounts', 0);

        $this->confirm($admin, $card['id'])->assertOk()->assertJsonPath('message', 'Đã tạo 1 voucher.');
        $voucher = Discount::query()->sole();
        $this->assertSame('shipping', $voucher->scope);
        $this->assertTrue($voucher->is_auto_apply);
        $this->assertSame('500000.00', $voucher->min_order_amount);

        // The same proposal cannot run twice.
        $this->confirm($admin, $card['id'])->assertStatus(410);
        $this->assertDatabaseCount('discounts', 1);
        $this->assertDatabaseHas('audit_logs', ['action' => 'ai.action', 'actor_id' => $admin->id]);
    }

    public function test_a_proposal_belongs_to_the_staff_member_it_was_made_for(): void
    {
        $admin = User::factory()->admin()->create();
        [$card] = $this->cards($admin, [['type' => 'create_vouchers', 'vouchers' => [[
            'scope' => 'order', 'name' => 'Giảm 50k', 'code' => 'GIAM50K', 'discount_type' => 'fixed', 'discount_value' => 50000, 'ends_at' => '2030-12-31',
        ]]]]);

        $this->confirm(User::factory()->admin()->create(), $card['id'])->assertStatus(410);
        $this->confirm($admin, $card['id'])->assertOk();
    }

    public function test_the_form_rules_apply_and_their_message_reaches_the_chat(): void
    {
        $admin = User::factory()->admin()->create();
        Discount::factory()->coupon()->create(['product_variant_id' => null, 'code' => 'GIAM50K']);

        $cards = $this->cards($admin, [
            ['type' => 'create_vouchers', 'vouchers' => [['scope' => 'order', 'name' => 'Thiếu hạn', 'code' => 'MOI', 'discount_type' => 'fixed', 'discount_value' => 1000]]],
            ['type' => 'create_vouchers', 'vouchers' => [['scope' => 'order', 'name' => 'Trùng', 'code' => 'giam50k', 'discount_type' => 'fixed', 'discount_value' => 1000, 'ends_at' => '2030-12-31']]],
            ['type' => 'create_vouchers', 'vouchers' => [
                ['scope' => 'order', 'name' => 'A', 'code' => 'HAI', 'discount_type' => 'fixed', 'discount_value' => 1000, 'ends_at' => '2030-12-31'],
                ['scope' => 'order', 'name' => 'B', 'code' => 'HAI', 'discount_type' => 'fixed', 'discount_value' => 2000, 'ends_at' => '2030-12-31'],
            ]],
        ]);

        $this->assertSame('Chưa có ngày kết thúc.', $cards[0]['error']);
        $this->assertSame('Mã này đã được dùng cho một giảm giá khác.', $cards[1]['error']);
        $this->assertSame('Voucher 2: mã HAI bị trùng với một voucher khác trong cùng lần tạo.', $cards[2]['error']);
        $this->assertArrayNotHasKey('id', $cards[0]);
    }

    public function test_actions_follow_the_staff_members_permissions(): void
    {
        $content = $this->staffWith('admin.access', 'ai.assistant', 'products.manage');

        [$card] = $this->cards($content, [['type' => 'create_vouchers', 'vouchers' => []]]);
        $this->assertStringContainsString('chưa có quyền', $card['error']);

        config(['services.ai.openai_compatible.endpoint' => 'https://ai.example.test/v1', 'services.ai.openai_compatible.key' => 'k']);
        Http::fake(['ai.example.test/*' => Http::response(['choices' => [['message' => ['content' => json_encode(['navigate' => 'products.index'])]]]])]);

        $this->actingAs($content)->postJson(route('admin.products.ai-assist'), ['messages' => [['role' => 'user', 'content' => [['type' => 'text', 'text' => 'chào']]]]])->assertOk();
        Http::assertSent(fn (HttpRequest $request) => ! str_contains(json_encode($request->data(), JSON_UNESCAPED_UNICODE), 'create_vouchers'));

        $this->actingAs(User::factory()->admin()->create())->postJson(route('admin.products.ai-assist'), ['messages' => [['role' => 'user', 'content' => [['type' => 'text', 'text' => 'chào']]]]])->assertOk();
        Http::assertSent(fn (HttpRequest $request) => str_contains(json_encode($request->data(), JSON_UNESCAPED_UNICODE), 'create_vouchers'));
    }

    public function test_bulk_variant_discount_reaches_every_matching_variant_and_nothing_else(): void
    {
        $admin = User::factory()->admin()->create();
        $levents = Brand::factory()->create(['name' => 'Levents']);
        $outerwear = Category::factory()->create(['name' => 'Áo khoác']);
        $bombers = Category::factory()->childOf($outerwear)->create(['name' => 'Bomber']);
        $jacket = Product::factory()->create(['brand_id' => $levents->id, 'category_id' => $bombers->id, 'status' => 'active']);
        $matching = ProductVariant::factory()->for($jacket)->count(2)->sequence(['size' => 'M'], ['size' => 'L'])->create(['is_active' => true]);
        $otherBrand = ProductVariant::factory()->for(Product::factory()->create(['category_id' => $bombers->id, 'status' => 'active']))->create(['is_active' => true]);
        $otherCategory = ProductVariant::factory()->for(Product::factory()->create(['brand_id' => $levents->id, 'status' => 'active']))->create(['is_active' => true]);

        [$card] = $this->cards($admin, [['type' => 'bulk_variant_discount', 'brand' => 'levents', 'category' => 'Áo khoác',
            'discount_type' => 'percent', 'discount_value' => 20, 'ends_at' => '2030-11-30']]);

        $this->assertArrayNotHasKey('error', $card, $card['error'] ?? '');
        $this->assertSame('Giảm 20% cho 2 biến thể của 1 sản phẩm', $card['title']);
        $this->confirm($admin, $card['id'])->assertOk();

        $this->assertEqualsCanonicalizing($matching->modelKeys(), Discount::query()->pluck('product_variant_id')->all());
        $this->assertDatabaseMissing('discounts', ['product_variant_id' => $otherBrand->id]);
        $this->assertDatabaseMissing('discounts', ['product_variant_id' => $otherCategory->id]);

        [$none] = $this->cards($admin, [['type' => 'bulk_variant_discount', 'discount_type' => 'percent', 'discount_value' => 20, 'ends_at' => '2030-11-30']]);
        $this->assertSame('Hãy cho biết giảm giá cho thương hiệu, danh mục hoặc sản phẩm nào.', $none['error']);
    }

    public function test_confirming_orders_skips_unpaid_transfers_and_logs_the_change(): void
    {
        $admin = User::factory()->admin()->create();
        $cod = Order::factory()->create(['status' => 'pending', 'payment_method' => 'cod', 'payment_status' => 'unpaid', 'placed_at' => now()->subHour()]);
        $paid = Order::factory()->create(['status' => 'pending', 'payment_method' => 'bank_transfer', 'payment_status' => 'paid', 'placed_at' => now()->subMinutes(30)]);
        $unpaid = Order::factory()->create(['status' => 'pending', 'payment_method' => 'bank_transfer', 'payment_status' => 'unpaid', 'placed_at' => now()->subMinutes(10)]);
        $shipped = Order::factory()->create(['status' => 'shipping', 'payment_method' => 'cod']);

        [$card] = $this->cards($admin, [['type' => 'confirm_orders']]);

        $this->assertSame('Xác nhận 2 đơn hàng', $card['title']);
        $this->assertSame(['Bỏ qua 1 đơn chuyển khoản chưa nhận tiền.'], $card['warnings']);
        $this->confirm($admin, $card['id'])->assertOk()->assertJsonPath('message', 'Đã xác nhận 2 đơn hàng.');

        $this->assertSame('confirmed', $cod->fresh()->status);
        $this->assertSame('confirmed', $paid->fresh()->status);
        $this->assertSame('pending', $unpaid->fresh()->status);
        $this->assertSame('shipping', $shipped->fresh()->status);
        $this->assertSame('Xác nhận hàng loạt qua trợ lý AI', collect($cod->fresh()->status_history)->last()['note']);
    }

    public function test_confirming_orders_needs_the_order_permission(): void
    {
        Order::factory()->create(['status' => 'pending', 'payment_method' => 'cod']);
        $viewer = $this->staffWith('admin.access', 'ai.assistant', 'orders.view');

        [$card] = $this->cards($viewer, [['type' => 'confirm_orders']]);

        $this->assertArrayHasKey('error', $card);
    }

    public function test_draft_receipts_go_to_each_variants_last_supplier_and_change_no_stock(): void
    {
        $admin = User::factory()->admin()->create();
        $coolmate = Supplier::factory()->create(['name' => 'Coolmate Supply']);
        $teelab = Supplier::factory()->create(['name' => 'Teelab Supply']);
        $low = fn (int $stock) => ProductVariant::factory()->for(Product::factory()->create(['status' => 'active']))
            ->create(['is_active' => true, 'stock_quantity' => $stock, 'low_stock_threshold' => 5]);
        [$a, $b, $c, $neverBought] = [$low(1), $low(5), $low(0), $low(2)];
        $plenty = ProductVariant::factory()->create(['is_active' => true, 'stock_quantity' => 50, 'low_stock_threshold' => 5]);

        $bought = function (Supplier $supplier, ProductVariant $variant, int $cost): void {
            $receipt = GoodsReceipt::factory()->confirmed()->create(['supplier_id' => $supplier->id]);
            GoodsReceiptItem::query()->create(['goods_receipt_id' => $receipt->id, 'product_variant_id' => $variant->id, 'quantity' => 10, 'cost_price' => $cost, 'subtotal' => 10 * $cost]);
        };
        $bought($coolmate, $a, 90000);
        $bought($coolmate, $b, 80000);
        $bought($teelab, $c, 70000);

        [$card] = $this->cards($admin, [['type' => 'draft_goods_receipts']]);

        $this->assertSame('Soạn 2 phiếu nhập nháp cho 3 biến thể sắp hết', $card['title']);
        $this->assertStringContainsString('1 biến thể chưa từng được nhập nên bị bỏ qua: '.$neverBought->sku, $card['warnings'][0]);
        $this->confirm($admin, $card['id'])->assertOk();

        $drafts = GoodsReceipt::query()->where('status', 'draft')->with('items')->get()->keyBy('supplier_id');
        $this->assertCount(2, $drafts);
        $this->assertEqualsCanonicalizing([$a->id, $b->id], $drafts[$coolmate->id]->items->pluck('product_variant_id')->all());
        $this->assertSame(9, $drafts[$coolmate->id]->items->firstWhere('product_variant_id', $a->id)->quantity);
        $this->assertSame('90000.00', $drafts[$coolmate->id]->items->firstWhere('product_variant_id', $a->id)->cost_price);
        $this->assertSame(10, $drafts[$teelab->id]->items->sole()->quantity);
        $this->assertSame(1, $a->fresh()->stock_quantity);
        $this->assertDatabaseMissing('goods_receipt_items', ['product_variant_id' => $plenty->id, 'goods_receipt_id' => $drafts[$coolmate->id]->id]);
    }

    public function test_an_unknown_or_tampered_proposal_id_does_nothing(): void
    {
        $this->confirm(User::factory()->admin()->create(), '00000000-0000-4000-8000-000000000000')->assertStatus(410);
        $this->actingAs(User::factory()->admin()->create())->postJson('/admin/tro-ly-ai/hanh-dong/khong-phai-uuid')->assertNotFound();
    }
}
