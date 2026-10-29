<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Order;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The admin's read-only view of customers: who they are, what they ordered
 * and spent, and where they ship.
 */
class AdminCustomerTest extends TestCase
{
    use RefreshDatabase;

    private function staffWith(string ...$codes): User
    {
        $role = Role::factory()->create();
        $role->permissions()->attach(Permission::query()->whereIn('code', $codes)->pluck('id'));

        return User::factory()->create(['role_id' => $role->id]);
    }

    private function viewer(): User
    {
        return $this->staffWith('admin.access', 'customers.view');
    }

    private function order(User $customer, string $status, string $payment, int $total, ?string $placedAt = null): Order
    {
        return Order::factory()->create([
            'user_id' => $customer->id, 'status' => $status, 'payment_status' => $payment,
            'grand_total' => $total, 'placed_at' => $placedAt ?? now()->subDay(),
        ]);
    }

    public function test_the_screens_need_the_customer_permission(): void
    {
        $customer = User::factory()->create();

        foreach ([route('admin.customers.index'), route('admin.customers.show', $customer)] as $url) {
            $this->actingAs($this->staffWith('admin.access', 'orders.view'))->get($url)->assertForbidden();
            $this->actingAs($this->viewer())->get($url)->assertOk();
        }
    }

    public function test_the_list_holds_customers_only_with_their_order_count_and_spending(): void
    {
        $an = User::factory()->create(['name' => 'Nguyễn An', 'email' => 'an@example.com']);
        $binh = User::factory()->create(['name' => 'Trần Bình']);
        $this->order($an, 'delivered', 'paid', 500_000);
        $this->order($an, 'delivered', 'paid', 300_000);
        $this->order($an, 'pending', 'unpaid', 900_000);
        $this->order($an, 'cancelled', 'unpaid', 700_000);
        $this->order($an, 'delivered', 'unpaid', 111_000);
        $staff = User::factory()->admin()->create(['name' => 'Quản Trị Viên']);

        $response = $this->actingAs($this->viewer())->get(route('admin.customers.index'))->assertOk();

        $response->assertSee('Nguyễn An')->assertSee('Trần Bình')->assertDontSee('Quản Trị Viên')->assertDontSee($staff->email);
        $customers = $response->viewData('customers')->keyBy('id');
        $this->assertSame([5, 800_000.0], [$customers[$an->id]->orders_count, (float) $customers[$an->id]->spent]);
        $this->assertSame([0, null], [$customers[$binh->id]->orders_count, $customers[$binh->id]->spent]);
        $response->assertSee('800.000');
    }

    public function test_search_matches_name_email_phone_and_address_phone(): void
    {
        $an = User::factory()->create(['name' => 'Nguyễn An', 'email' => 'an@example.com', 'phone' => '0901111111']);
        $binh = User::factory()->create(['name' => 'Trần Bình', 'email' => 'binh@example.com', 'phone' => '0902222222']);
        $chi = User::factory()->create(['name' => 'Lê Chi', 'email' => 'chi@example.com', 'phone' => null]);
        Address::factory()->create(['user_id' => $chi->id, 'phone' => '0903333333']);
        $viewer = $this->viewer();

        $found = fn (string $q): array => $this->actingAs($viewer)->get(route('admin.customers.index', ['q' => $q]))
            ->viewData('customers')->pluck('id')->all();

        $this->assertSame([$an->id], $found('Nguyễn'));
        $this->assertSame([$binh->id], $found('binh@'));
        $this->assertSame([$an->id], $found('0901111'));
        $this->assertSame([$chi->id], $found('0903333333'));
        $this->assertSame([], $found('khong-co-ai'));
        $this->assertSame([], $found('%%'), 'wildcards are plain text');
    }

    public function test_the_bought_filter_and_sorting(): void
    {
        $few = User::factory()->create(['name' => 'B Ít mua']);
        $many = User::factory()->create(['name' => 'C Mua nhiều']);
        $never = User::factory()->create(['name' => 'A Chưa mua']);
        $this->order($few, 'delivered', 'paid', 100_000);
        $this->order($many, 'delivered', 'paid', 400_000);
        $this->order($many, 'delivered', 'paid', 500_000);
        $viewer = $this->viewer();

        $ids = fn (array $query): array => $this->actingAs($viewer)->get(route('admin.customers.index', $query))->viewData('customers')->pluck('id')->all();

        $this->assertEqualsCanonicalizing([$few->id, $many->id], $ids(['mua' => 'bought']));
        $this->assertSame([$never->id], $ids(['mua' => 'never']));
        $this->assertSame([$many->id, $few->id, $never->id], $ids(['sort' => 'spent', 'direction' => 'desc']));
        $this->assertSame([$many->id, $few->id], array_slice($ids(['sort' => 'orders_count', 'direction' => 'desc']), 0, 2));
        $this->assertSame([$never->id, $few->id, $many->id], $ids(['sort' => 'name', 'direction' => 'asc']));
        $this->assertCount(3, $ids(['sort' => 'password', 'mua' => 'bogus']), 'unknown values are ignored');
    }

    public function test_the_detail_shows_the_customers_own_addresses_and_orders(): void
    {
        $an = User::factory()->create(['name' => 'Nguyễn An', 'email' => 'an@example.com', 'phone' => '0901111111']);
        $other = User::factory()->create(['name' => 'Người Khác']);
        Address::factory()->create(['user_id' => $an->id, 'label' => 'Nhà riêng', 'recipient_name' => 'Nguyễn An', 'is_default' => false, 'address_line' => '12 Lê Lợi']);
        Address::factory()->create(['user_id' => $an->id, 'label' => 'Công ty', 'is_default' => true, 'address_line' => '34 Hai Bà Trưng']);
        Address::factory()->create(['user_id' => $other->id, 'label' => 'Của người khác', 'address_line' => '99 Nơi Khác']);
        $this->order($an, 'delivered', 'paid', 500_000, '2026-10-10 09:00:00');
        $this->order($an, 'delivered', 'paid', 300_000, '2026-10-20 09:00:00');
        $this->order($an, 'pending', 'unpaid', 900_000, '2026-10-25 09:00:00');
        $mine = Order::query()->where('user_id', $an->id)->pluck('order_number');
        $theirs = $this->order($other, 'delivered', 'paid', 123_000);

        $response = $this->actingAs($this->staffWith('admin.access', 'customers.view', 'orders.view'))->get(route('admin.customers.show', $an))->assertOk();

        $response->assertSee('an@example.com')->assertSee('0901111111')->assertSee('12 Lê Lợi')->assertSee('34 Hai Bà Trưng')
            ->assertDontSee('99 Nơi Khác')->assertDontSee($theirs->order_number);
        $this->assertSame(['Công ty', 'Nhà riêng'], $response->viewData('addresses')->pluck('label')->all(), 'the default address first');
        $response->assertSeeInOrder($mine->sortByDesc(fn ($number) => Order::query()->where('order_number', $number)->value('placed_at'))->values()->all());
        $this->assertSame(['orders' => 3, 'spent' => 800_000.0, 'average' => 400_000.0, 'last_order_at' => '2026-10-25 09:00:00'], $response->viewData('stats'));
        foreach ($mine as $number) {
            $response->assertSee(route('admin.orders.show', Order::query()->where('order_number', $number)->first()), false);
        }
    }

    public function test_order_links_need_the_order_permission(): void
    {
        $customer = User::factory()->create();
        $order = $this->order($customer, 'delivered', 'paid', 100_000);

        $response = $this->actingAs($this->viewer())->get(route('admin.customers.show', $customer))->assertOk();

        $response->assertSee($order->order_number)->assertDontSee(route('admin.orders.show', $order), false);
    }

    public function test_staff_accounts_are_not_customers(): void
    {
        $staff = User::factory()->admin()->create();

        $this->actingAs($this->viewer())->get(route('admin.customers.show', $staff))->assertNotFound();
    }
}
