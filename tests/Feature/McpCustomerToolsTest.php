<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Order;
use App\Models\User;
use App\Services\Reports\CustomerStats;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CallsMcpTools;
use Tests\TestCase;

/**
 * The MCP tools about customers: finding one, their history, who spends the
 * most and who stopped coming back. Personal details stay masked.
 */
class McpCustomerToolsTest extends TestCase
{
    use CallsMcpTools;
    use RefreshDatabase;

    private const TOOLS = ['find_customer', 'get_customer', 'lapsed_customers', 'top_customers'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-10-29 10:00:00'));
    }

    private function customer(array $attributes = []): User
    {
        return User::factory()->create($attributes);
    }

    private function order(User $customer, string $status, string $payment, int $total, string $placedAt = '2026-10-20 09:00:00', array $extra = []): Order
    {
        return Order::factory()->create([
            'user_id' => $customer->id, 'status' => $status, 'payment_status' => $payment,
            'grand_total' => $total, 'placed_at' => $placedAt, ...$extra,
        ]);
    }

    private function token(): string
    {
        return $this->tokenFor(['customers.view']);
    }

    public function test_only_roles_with_the_customer_permission_see_the_customer_tools(): void
    {
        $this->assertSame(self::TOOLS, $this->toolNames($this->token()));
        $this->assertSame([], array_intersect(self::TOOLS, $this->toolNames($this->tokenFor(['dashboard.view', 'orders.view', 'inventory.manage']))));
    }

    public function test_find_customer_matches_phone_email_name_order_number_and_address_phone(): void
    {
        $an = $this->customer(['name' => 'Nguyễn Văn An', 'email' => 'an.nguyen@example.com', 'phone' => '0901234567']);
        $binh = $this->customer(['name' => 'Trần Bình', 'email' => 'binh@example.com', 'phone' => '0907654321']);
        $chi = $this->customer(['name' => 'Lê Chi', 'email' => 'chi@example.com', 'phone' => null]);
        Address::factory()->create(['user_id' => $chi->id, 'phone' => '0933445566']);
        $this->order($binh, 'delivered', 'paid', 400_000, extra: ['order_number' => 'DH261020ABCDEF']);
        $token = $this->token();

        $found = fn (string $query): array => array_column($this->callTool($token, 'find_customer', ['query' => $query])['structuredContent']['customers'], 'id');

        $this->assertSame([$an->id], $found('0901234567'));
        $this->assertSame([$an->id], $found('an.nguyen@'));
        $this->assertSame([$an->id], $found('Nguyễn Văn'));
        $this->assertSame([$binh->id], $found('dh261020abcdef'), 'an order number, any case');
        $this->assertSame([$chi->id], $found('0933445566'));
        $this->assertSame([], $found('khong-co-ai'));
        $this->assertSame([], $found('%%%'), 'wildcards are plain text');
    }

    public function test_find_customer_hides_personal_details_and_is_limited_to_customers(): void
    {
        $an = $this->customer(['name' => 'Nguyễn Văn An', 'email' => 'an.nguyen@example.com', 'phone' => '0901234567']);
        $staff = User::factory()->admin()->create(['name' => 'Nguyễn Quản Trị', 'phone' => '0911111111']);
        Address::factory()->create(['user_id' => $an->id, 'address_line' => '12 Lê Lợi']);
        $this->order($an, 'delivered', 'paid', 500_000, extra: ['customer_phone' => '0909999999', 'shipping_address' => '12 Lê Lợi']);

        $result = $this->callTool($this->token(), 'find_customer', ['query' => 'Nguyễn']);
        $row = $result['structuredContent']['customers'][0];
        $raw = json_encode($result, JSON_UNESCAPED_UNICODE);

        $this->assertSame(1, $result['structuredContent']['total_matches'], 'staff are not customers');
        $this->assertSame([$an->id, 'N. V. An', '***567', route('admin.customers.show', $an)], [$row['id'], $row['name'], $row['phone_last3'], $row['admin_url']]);
        foreach (['Nguyễn Văn An', 'an.nguyen@example.com', '0901234567', '0909999999', '12 Lê Lợi', 'Quản Trị', '0911111111'] as $secret) {
            $this->assertStringNotContainsString($secret, $raw);
        }
    }

    public function test_find_customer_needs_three_characters_and_caps_the_limit(): void
    {
        $token = $this->token();

        $this->assertTrue($this->callTool($token, 'find_customer', ['query' => 'an'])['isError']);
        $this->assertTrue($this->callTool($token, 'find_customer')['isError']);
        $this->assertTrue($this->callTool($token, 'find_customer', ['query' => 'abc', 'limit' => 11])['isError']);

        foreach (range(1, 3) as $n) {
            $this->customer(['name' => "Khách Thử {$n}"]);
        }
        $limited = $this->callTool($token, 'find_customer', ['query' => 'Khách Thử', 'limit' => 2])['structuredContent'];
        $this->assertSame([3, 2], [$limited['total_matches'], count($limited['customers'])]);
    }

    public function test_the_numbers_are_the_ones_the_customer_screen_shows(): void
    {
        $an = $this->customer(['name' => 'Nguyễn An']);
        $this->order($an, 'delivered', 'paid', 500_000);
        $this->order($an, 'delivered', 'paid', 300_000);
        $this->order($an, 'delivered', 'unpaid', 111_000);
        $this->order($an, 'pending', 'unpaid', 900_000);
        $this->order($an, 'cancelled', 'unpaid', 700_000);
        $token = $this->token();

        $row = $this->callTool($token, 'find_customer', ['query' => 'Nguyễn An'])['structuredContent']['customers'][0];
        $screen = CustomerStats::customers()->find($an->id);
        $page = $this->actingAs($this->staffWithCustomerView())->get(route('admin.customers.index'))->viewData('customers')->keyBy('id')[$an->id];

        $this->assertSame([5, 800_000], [$row['orders'], $row['spent']], 'cancelled orders count as orders; only delivered and paid is spent');
        $this->assertSame([$screen->orders_count, (int) $screen->spent], [$row['orders'], $row['spent']]);
        $this->assertSame([$page->orders_count, (int) $page->spent], [$row['orders'], $row['spent']]);
    }

    private function staffWithCustomerView(): User
    {
        return User::factory()->create(['role_id' => $this->roleWith(['admin.access', 'customers.view'])->id]);
    }

    public function test_get_customer_summarises_one_customer_without_personal_details(): void
    {
        $an = $this->customer(['name' => 'Nguyễn Văn An', 'email' => 'an@example.com', 'phone' => '0901234567']);
        Address::factory()->count(2)->create(['user_id' => $an->id, 'address_line' => '12 Lê Lợi']);
        $this->order($an, 'delivered', 'paid', 500_000, '2026-09-01 09:00:00', ['province_name' => 'TP. Hồ Chí Minh']);
        $this->order($an, 'delivered', 'paid', 300_000, '2026-10-01 09:00:00', ['province_name' => 'Hà Nội']);
        $this->order($an, 'cancelled', 'unpaid', 700_000, '2026-10-10 09:00:00', ['province_name' => 'Hà Nội']);
        $this->order($an, 'pending', 'unpaid', 900_000, '2026-10-25 09:00:00', ['province_name' => 'Đà Nẵng', 'customer_note' => 'Gọi 0988777666 trước khi giao']);
        $other = $this->customer();
        $this->order($other, 'delivered', 'paid', 123_000);

        $result = $this->callTool($this->token(), 'get_customer', ['customer_id' => $an->id]);
        $data = $result['structuredContent'];
        $raw = json_encode($result, JSON_UNESCAPED_UNICODE);

        $this->assertFalse($result['isError']);
        $this->assertSame(['N. V. An', '***567'], [$data['name'], $data['phone_last3']]);
        $this->assertSame([4, 2, 800_000, 400_000, 1, 25, 2], [$data['orders'], $data['delivered_orders'], $data['spent'], $data['average_order_value'], $data['cancelled_orders'], $data['cancel_rate_percent'], $data['saved_addresses']]);
        $this->assertEqualsCanonicalizing(['Hà Nội', 'TP. Hồ Chí Minh', 'Đà Nẵng'], $data['shipped_to_provinces']);
        $this->assertSame(['Chờ xác nhận', 'Đã hủy', 'Đã giao hàng', 'Đã giao hàng'], array_column($data['recent_orders'], 'status'), 'newest first');
        $this->assertSame(route('admin.customers.show', $an), $data['admin_url']);
        foreach (['Nguyễn Văn An', 'an@example.com', '0901234567', '12 Lê Lợi', 'Gọi 0988777666', '123000'] as $secret) {
            $this->assertStringNotContainsString($secret, $raw);
        }
    }

    public function test_get_customer_keeps_the_ten_most_recent_orders_and_rejects_unknown_ids(): void
    {
        $an = $this->customer();
        foreach (range(1, 12) as $day) {
            $this->order($an, 'delivered', 'paid', 100_000, sprintf('2026-10-%02d 09:00:00', $day));
        }
        $staff = User::factory()->admin()->create();
        $token = $this->token();

        $data = $this->callTool($token, 'get_customer', ['customer_id' => $an->id])['structuredContent'];
        $this->assertSame([12, 10], [$data['orders'], count($data['recent_orders'])]);
        $this->assertSame('2026-10-12', substr($data['recent_orders'][0]['placed_at'], 0, 10));

        $missing = $this->callTool($token, 'get_customer', ['customer_id' => 999_999]);
        $this->assertTrue($missing['isError']);
        $this->assertStringContainsString('Không có khách hàng id 999999', $missing['content'][0]['text']);
        $this->assertTrue($this->callTool($token, 'get_customer', ['customer_id' => $staff->id])['isError'], 'a staff id is not a customer');
        $this->assertTrue($this->callTool($token, 'get_customer')['isError']);
    }

    public function test_top_customers_ranks_delivered_and_paid_spending_in_the_period(): void
    {
        $big = $this->customer(['name' => 'Khách Lớn']);
        $small = $this->customer(['name' => 'Khách Nhỏ']);
        $old = $this->customer(['name' => 'Khách Cũ']);
        $unpaid = $this->customer(['name' => 'Khách Chưa Trả']);
        $this->order($big, 'delivered', 'paid', 600_000, '2026-10-25 09:00:00');
        $this->order($big, 'delivered', 'paid', 400_000, '2026-10-26 09:00:00');
        $this->order($small, 'delivered', 'paid', 250_000, '2026-10-27 09:00:00');
        $this->order($old, 'delivered', 'paid', 9_000_000, '2026-07-01 09:00:00');
        $this->order($unpaid, 'delivered', 'unpaid', 5_000_000, '2026-10-27 09:00:00');
        $this->order($unpaid, 'cancelled', 'unpaid', 5_000_000, '2026-10-27 09:00:00');
        $token = $this->token();

        $month = $this->callTool($token, 'top_customers', ['period' => '30-ngay'])['structuredContent'];
        $this->assertSame('30-ngay', $month['period']['key']);
        $this->assertSame([[$big->id, 2, 1_000_000], [$small->id, 1, 250_000]], array_map(fn ($row) => [$row['id'], $row['delivered_orders'], $row['spent']], $month['customers']));
        $this->assertSame('K. Lớn', $month['customers'][0]['name']);

        $all = $this->callTool($token, 'top_customers', ['period' => 'tat-ca', 'limit' => 1])['structuredContent'];
        $this->assertSame([$old->id], array_column($all['customers'], 'id'));

        $this->assertTrue($this->callTool($token, 'top_customers', ['period' => 'thang-9'])['isError']);
        $this->assertTrue($this->callTool($token, 'top_customers', ['limit' => 21])['isError']);
    }

    public function test_lapsed_customers_are_repeat_buyers_with_no_recent_order(): void
    {
        $lapsed = $this->customer(['name' => 'Khách Quên']);
        $lapsedBig = $this->customer(['name' => 'Khách Quên Lớn']);
        $recent = $this->customer(['name' => 'Khách Mới Mua']);
        $pendingRecent = $this->customer(['name' => 'Khách Vừa Đặt']);
        $once = $this->customer(['name' => 'Khách Một Lần']);
        $unpaid = $this->customer(['name' => 'Khách Chưa Trả']);
        foreach ([$lapsed, $lapsedBig, $recent, $pendingRecent, $unpaid] as $customer) {
            $this->order($customer, 'delivered', 'paid', 200_000, '2026-06-01 09:00:00');
        }
        $this->order($lapsed, 'delivered', 'paid', 300_000, '2026-07-01 09:00:00');
        $this->order($lapsedBig, 'delivered', 'paid', 900_000, '2026-06-15 09:00:00');
        $this->order($recent, 'delivered', 'paid', 300_000, '2026-10-20 09:00:00');
        $this->order($pendingRecent, 'delivered', 'paid', 100_000, '2026-06-10 09:00:00');
        $this->order($pendingRecent, 'pending', 'unpaid', 300_000, '2026-10-28 09:00:00');
        $this->order($unpaid, 'delivered', 'unpaid', 300_000, '2026-07-01 09:00:00');
        $this->order($once, 'delivered', 'paid', 800_000, '2026-06-01 09:00:00');

        $data = $this->callTool($this->token(), 'lapsed_customers')['structuredContent'];

        $this->assertSame([$lapsedBig->id, $lapsed->id], array_column($data['customers'], 'id'), 'biggest spenders first');
        $this->assertSame([2, 60], [$data['min_orders'], $data['days']]);
        $this->assertSame([2, 1_100_000, '2026-06-15', 136], [$data['customers'][0]['delivered_orders'], $data['customers'][0]['spent'], substr($data['customers'][0]['last_order_at'], 0, 10), $data['customers'][0]['days_since_last_order']]);

        $once = $this->callTool($this->token(), 'lapsed_customers', ['min_orders' => 1, 'days' => 100])['structuredContent'];
        $this->assertContains('Khách Một Lần', array_map(fn ($row) => $this->customerName($row['id']), $once['customers']));
    }

    private function customerName(int $id): string
    {
        return User::query()->findOrFail($id)->name;
    }
}
