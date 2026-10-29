<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CallsMcpTools;
use Tests\TestCase;

/**
 * The MCP tools that look things up for the shop's staff: orders waiting for
 * a decision, one order, and stock by name or SKU.
 */
class McpLookupToolsTest extends TestCase
{
    use CallsMcpTools;
    use RefreshDatabase;

    private function pending(string $number, string $method, string $payment, \DateTimeInterface $placedAt, array $extra = []): Order
    {
        return Order::factory()->create([
            'order_number' => $number, 'status' => 'pending', 'payment_method' => $method,
            'payment_status' => $payment, 'placed_at' => $placedAt, ...$extra,
        ]);
    }

    public function test_orders_to_process_separates_orders_that_can_be_confirmed_from_unpaid_transfers(): void
    {
        $this->pending('DH-COD', 'cod', 'unpaid', now()->subHours(5), ['customer_name' => 'Nguyễn Văn An', 'grand_total' => 300_000]);
        $this->pending('DH-PAID', 'bank_transfer', 'paid', now()->subHours(2));
        $this->pending('DH-WAIT', 'bank_transfer', 'unpaid', now()->subMinutes(30));
        Order::factory()->create(['status' => 'shipping', 'payment_method' => 'cod']);

        $result = $this->callTool($this->tokenFor(['orders.view']), 'orders_to_process');
        $data = $result['structuredContent'];

        $this->assertFalse($result['isError']);
        $this->assertSame([3, 2, 1], [$data['pending_total'], $data['ready_to_confirm'], $data['waiting_for_payment']]);
        $this->assertSame(['DH-COD', 'DH-PAID', 'DH-WAIT'], array_column($data['orders'], 'order_number'), 'oldest first');
        $this->assertSame([true, true, false], array_column($data['orders'], 'can_confirm'));
        $this->assertSame([5, 2, 0], array_column($data['orders'], 'hours_waiting'));
        $this->assertSame('N. V. An', $data['orders'][0]['customer']);
        $this->assertSame(300_000, $data['orders'][0]['grand_total']);
        $this->assertStringNotContainsString('Nguyễn Văn An', json_encode($result, JSON_UNESCAPED_UNICODE));
    }

    public function test_orders_to_process_can_be_narrowed_by_payment_method_and_limit(): void
    {
        $this->pending('DH-A', 'cod', 'unpaid', now()->subHours(3));
        $this->pending('DH-B', 'bank_transfer', 'paid', now()->subHours(2));
        $this->pending('DH-C', 'bank_transfer', 'unpaid', now()->subHour());
        $token = $this->tokenFor(['orders.manage']);

        $transfers = $this->callTool($token, 'orders_to_process', ['payment_method' => 'bank_transfer'])['structuredContent'];
        $this->assertSame([2, 1, 1], [$transfers['pending_total'], $transfers['ready_to_confirm'], $transfers['waiting_for_payment']]);
        $this->assertSame(['DH-B', 'DH-C'], array_column($transfers['orders'], 'order_number'));

        $first = $this->callTool($token, 'orders_to_process', ['limit' => 1])['structuredContent'];
        $this->assertSame(3, $first['pending_total']);
        $this->assertSame(['DH-A'], array_column($first['orders'], 'order_number'));

        $this->assertTrue($this->callTool($token, 'orders_to_process', ['payment_method' => 'momo'])['isError']);
        $this->assertTrue($this->callTool($token, 'orders_to_process', ['limit' => 51])['isError']);
    }

    public function test_get_order_returns_the_order_without_personal_details(): void
    {
        $order = Order::factory()->create([
            'order_number' => 'DH261024YZEBRU', 'status' => 'confirmed', 'payment_method' => 'bank_transfer', 'payment_status' => 'paid',
            'customer_name' => 'Nguyễn Văn An', 'customer_email' => 'an@example.com', 'customer_phone' => '0901234567',
            'shipping_address' => '12 Lê Lợi', 'ward_name' => 'Phường Bến Nghé', 'province_name' => 'TP. Hồ Chí Minh',
            'customer_note' => 'Gọi trước khi giao 0988777666', 'transaction_code' => 'FT26102412345',
            'subtotal' => 500_000, 'discount_amount' => 50_000, 'shipping_fee' => 30_000, 'shipping_discount_amount' => 30_000, 'grand_total' => 450_000,
            'status_history' => [['from' => 'pending', 'to' => 'confirmed', 'actor_id' => 1, 'note' => 'Đã gọi khách', 'at' => '2026-10-24T10:00:00+07:00']],
        ]);
        $order->items()->create([
            'product_name' => 'Áo thun', 'sku' => 'AT-M-DEN', 'size_name' => 'M', 'color_name' => 'Đen',
            'original_unit_price' => 250_000, 'unit_price' => 250_000, 'quantity' => 2, 'line_total' => 500_000,
        ]);

        $result = $this->callTool($this->tokenFor(['orders.view']), 'get_order', ['order_number' => ' dh261024yzebru ']);
        $data = $result['structuredContent'];
        $raw = json_encode($result, JSON_UNESCAPED_UNICODE);

        $this->assertFalse($result['isError']);
        $this->assertSame(['DH261024YZEBRU', 'confirmed', 'Đã xác nhận', 'Đã thanh toán', 'N. V. An', 'TP. Hồ Chí Minh'],
            [$data['order_number'], $data['status'], $data['status_label'], $data['payment_status'], $data['customer'], $data['province']]);
        $this->assertSame([500_000, 50_000, 30_000, 30_000, 450_000],
            [$data['subtotal'], $data['discount_amount'], $data['shipping_fee'], $data['shipping_discount_amount'], $data['grand_total']]);
        $this->assertSame([['product' => 'Áo thun', 'sku' => 'AT-M-DEN', 'size' => 'M', 'color' => 'Đen', 'quantity' => 2, 'unit_price' => 250_000, 'line_total' => 500_000]], $data['items']);
        $this->assertSame([['from' => 'Chờ xác nhận', 'to' => 'Đã xác nhận', 'at' => '2026-10-24T10:00:00+07:00', 'note' => 'Đã gọi khách']], $data['history']);
        foreach (['Nguyễn Văn An', 'an@example.com', '0901234567', '12 Lê Lợi', 'Bến Nghé', 'Gọi trước khi giao', 'FT26102412345'] as $secret) {
            $this->assertStringNotContainsString($secret, $raw);
        }
    }

    public function test_get_order_says_so_when_the_order_does_not_exist(): void
    {
        $result = $this->callTool($this->tokenFor(['orders.view']), 'get_order', ['order_number' => 'DH-KHONG-CO']);

        $this->assertTrue($result['isError']);
        $this->assertStringContainsString('Không có đơn hàng mã DH-KHONG-CO', $result['content'][0]['text']);
        $this->assertTrue($this->callTool($this->tokenFor(['orders.view']), 'get_order')['isError']);
    }

    public function test_find_stock_matches_names_and_skus_and_can_be_narrowed_by_brand(): void
    {
        $levents = Brand::factory()->create(['name' => 'Levents']);
        $bomber = Product::factory()->create(['name' => 'Áo khoác bomber', 'brand_id' => $levents->id, 'status' => 'active']);
        ProductVariant::factory()->for($bomber)->create(['sku' => 'LV-BOM-M', 'size' => 'M', 'color' => 'Đen', 'stock_quantity' => 12, 'low_stock_threshold' => 5, 'is_active' => true]);
        ProductVariant::factory()->for($bomber)->create(['sku' => 'LV-BOM-L', 'size' => 'L', 'color' => 'Đen', 'stock_quantity' => 2, 'low_stock_threshold' => 5, 'is_active' => true]);
        ProductVariant::factory()->for($bomber)->create(['sku' => 'LV-BOM-XL', 'size' => 'XL', 'color' => 'Đen', 'stock_quantity' => 9, 'low_stock_threshold' => 5, 'is_active' => false]);
        $other = Product::factory()->create(['name' => 'Áo khoác gió', 'brand_id' => Brand::factory()->create(['name' => 'Coolmate'])->id, 'status' => 'active']);
        ProductVariant::factory()->for($other)->create(['sku' => 'CM-GIO-M', 'stock_quantity' => 30, 'low_stock_threshold' => 5, 'is_active' => true]);
        $token = $this->tokenFor(['inventory.manage']);

        $byName = $this->callTool($token, 'find_stock', ['query' => 'khoác'])['structuredContent'];
        $this->assertSame(4, $byName['total_matches']);

        $byBrand = $this->callTool($token, 'find_stock', ['query' => 'khoác', 'brand' => 'levent'])['structuredContent'];
        $this->assertSame(3, $byBrand['total_matches']);
        $rows = collect($byBrand['variants'])->keyBy('sku');
        $this->assertSame(['Levents', 'Áo khoác bomber', 12, 5, true, false], [$rows['LV-BOM-M']['brand'], $rows['LV-BOM-M']['product'], $rows['LV-BOM-M']['stock'], $rows['LV-BOM-M']['threshold'], $rows['LV-BOM-M']['on_sale'], $rows['LV-BOM-M']['low']]);
        $this->assertTrue($rows['LV-BOM-L']['low']);
        $this->assertFalse($rows['LV-BOM-XL']['on_sale']);

        $bySku = $this->callTool($token, 'find_stock', ['query' => 'CM-GIO'])['structuredContent'];
        $this->assertSame(['CM-GIO-M'], array_column($bySku['variants'], 'sku'));

        $limited = $this->callTool($token, 'find_stock', ['query' => 'khoác', 'limit' => 1])['structuredContent'];
        $this->assertSame([4, 1], [$limited['total_matches'], count($limited['variants'])]);
    }

    public function test_find_stock_treats_wildcards_as_plain_text_and_needs_a_query(): void
    {
        ProductVariant::factory()->create(['sku' => 'AT-M', 'is_active' => true]);
        $token = $this->tokenFor(['dashboard.view']);

        $this->assertSame(0, $this->callTool($token, 'find_stock', ['query' => '%%'])['structuredContent']['total_matches']);
        $this->assertSame(0, $this->callTool($token, 'find_stock', ['query' => '__'])['structuredContent']['total_matches']);
        $this->assertTrue($this->callTool($token, 'find_stock', ['query' => 'a'])['isError']);
        $this->assertTrue($this->callTool($token, 'find_stock')['isError']);
    }

    public function test_low_stock_suggests_how_many_to_order(): void
    {
        $product = Product::factory()->create(['status' => 'active']);
        ProductVariant::factory()->for($product)->create(['sku' => 'A-1', 'size' => 'S', 'color' => 'Đen', 'stock_quantity' => 0, 'low_stock_threshold' => 5, 'is_active' => true]);
        ProductVariant::factory()->for($product)->create(['sku' => 'A-2', 'size' => 'M', 'color' => 'Đen', 'stock_quantity' => 3, 'low_stock_threshold' => 5, 'is_active' => true]);
        ProductVariant::factory()->for($product)->create(['sku' => 'A-3', 'size' => 'L', 'color' => 'Đen', 'stock_quantity' => 5, 'low_stock_threshold' => 5, 'is_active' => true]);

        $rows = collect($this->callTool($this->tokenFor(['inventory.manage']), 'low_stock')['structuredContent']['variants'])->pluck('suggested_quantity', 'sku')->all();

        $this->assertSame(['A-1' => 10, 'A-2' => 7, 'A-3' => 5], $rows);
    }

    public function test_the_lookup_tools_follow_the_roles_permissions(): void
    {
        $this->assertContains('orders_to_process', $this->toolNames($this->tokenFor(['orders.manage'])));
        $this->assertContains('get_order', $this->toolNames($this->tokenFor(['orders.manage'])));
        $this->assertNotContains('find_stock', $this->toolNames($this->tokenFor(['orders.view'])));
        $this->assertNotContains('get_order', $this->toolNames($this->tokenFor(['dashboard.view'])));
        $this->assertNotContains('orders_to_process', $this->toolNames($this->tokenFor(['inventory.manage'])));
    }
}
