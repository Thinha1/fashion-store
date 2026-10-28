<?php

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\GoodsReceipt;
use App\Models\Order;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\AdminNavigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffPermissionsTest extends TestCase
{
    use RefreshDatabase;

    private function roleWith(string ...$codes): Role
    {
        $role = Role::factory()->create();
        $role->permissions()->attach(Permission::query()->whereIn('code', $codes)->pluck('id'));

        return $role;
    }

    private function staffWith(string ...$codes): User
    {
        return User::factory()->create(['role_id' => $this->roleWith(...$codes)->id]);
    }

    private function staffIn(string $roleCode): User
    {
        return User::factory()->create(['role_id' => Role::query()->where('code', $roleCode)->value('id')]);
    }

    /**
     * @return list<string>
     */
    private function codes(Role $role): array
    {
        return $role->permissions()->orderBy('code')->pluck('code')->all();
    }

    public function test_the_catalog_is_split_and_default_roles_are_ready(): void
    {
        $codes = Permission::query()->pluck('code');
        foreach (['dashboard.view', 'roles.manage', 'settings.ai', 'settings.payment', 'catalog.manage', 'discounts.manage', 'ai.assistant', 'inventory.confirm', 'orders.view', 'payments.confirm', 'customers.view'] as $code) {
            $this->assertContains($code, $codes);
        }
        foreach (['settings.manage', 'audit_logs.view', 'customers.manage', 'payments.manage'] as $code) {
            $this->assertNotContains($code, $codes);
        }

        $warehouse = Role::query()->where('code', 'warehouse')->sole();
        $this->assertSame(['admin.access', 'inventory.manage', 'suppliers.manage'], $this->codes($warehouse));
        $this->assertSame(['admin.access', 'customers.view', 'orders.manage', 'orders.view'], $this->codes(Role::query()->where('code', 'sales')->sole()));
        $this->assertNotContains('roles.manage', $this->codes(Role::query()->where('code', 'store-manager')->sole()));
    }

    public function test_the_sidebar_and_landing_page_follow_the_role(): void
    {
        $warehouse = $this->staffIn('warehouse');
        $this->assertSame(['Kho & cung ứng'], array_keys(AdminNavigation::for($warehouse)));

        $this->actingAs($warehouse)->get(route('admin.dashboard'))->assertRedirect(route('admin.suppliers.index'));
        $this->actingAs($warehouse)->get(route('admin.suppliers.index'))
            ->assertOk()
            ->assertSee('Phiếu nhập hàng')
            ->assertDontSee(route('admin.products.index'), false)
            ->assertDontSee(route('admin.roles.index'), false);

        $this->actingAs($this->staffWith('admin.access'))->get(route('admin.dashboard'))
            ->assertOk()->assertSee('chưa được cấp quyền');
    }

    public function test_each_split_permission_guards_its_own_screens(): void
    {
        $content = $this->staffIn('content');
        $this->actingAs($content)->get(route('admin.brands.index'))->assertOk();
        $this->actingAs($content)->get(route('admin.size-charts.index'))->assertOk();
        $this->actingAs($content)->get(route('admin.discounts.index'))->assertForbidden();
        $this->actingAs($content)->get(route('admin.orders.index'))->assertForbidden();

        $sales = $this->staffIn('sales');
        $order = Order::factory()->create(['payment_method' => 'bank_transfer', 'payment_status' => 'unpaid']);
        $this->actingAs($sales)->get(route('admin.orders.show', $order))->assertOk()->assertDontSee('Xác nhận đã nhận tiền');
        $this->actingAs($sales)->patch(route('admin.orders.confirm-payment', $order))->assertForbidden();
        $this->actingAs($sales)->get(route('admin.settings.payment.edit'))->assertForbidden();

        $receipt = GoodsReceipt::factory()->create();
        $this->actingAs($this->staffIn('warehouse'))->post(route('admin.goods-receipts.confirm', $receipt))->assertForbidden();
        $this->assertSame('draft', $receipt->fresh()->status);
    }

    public function test_cancelling_a_paid_order_needs_payments_confirm(): void
    {
        $order = Order::factory()->create(['status' => 'confirmed', 'payment_method' => 'bank_transfer', 'payment_status' => 'paid']);

        $this->actingAs($this->staffIn('sales'))
            ->patch(route('admin.orders.status', $order), ['status' => 'cancelled', 'note' => 'Khách đổi ý'])
            ->assertSessionHas('error');
        $this->assertSame('confirmed', $order->fresh()->status);
    }

    public function test_a_role_is_created_with_the_permissions_ticked_and_logged(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.roles.store'), [
            'name' => 'Thu ngân', 'description' => 'Ca tối', 'permissions' => ['admin.access', 'orders.view'],
        ])->assertRedirect(route('admin.roles.index'));

        $role = Role::query()->where('code', 'thu-ngan')->sole();
        $this->assertSame(['admin.access', 'orders.view'], $this->codes($role));
        $log = AuditLog::query()->where('action', 'role.created')->sole();
        $this->assertSame(['admin.access', 'orders.view'], $log->new_values['permissions']);

        $this->actingAs($admin)->get(route('admin.roles.index'))->assertOk()->assertSee('Thu ngân');
        $this->actingAs($admin)->get(route('admin.roles.edit', $role))->assertOk()->assertSee('orders.view');
    }

    public function test_nobody_grants_more_than_they_hold(): void
    {
        $manager = $this->staffWith('admin.access', 'roles.manage', 'staff.manage', 'orders.view');
        $role = $this->roleWith('admin.access');

        $this->actingAs($manager)->put(route('admin.roles.update', $role), [
            'name' => $role->name, 'permissions' => ['admin.access', 'settings.payment'],
        ])->assertSessionHas('error');
        $this->assertSame(['admin.access'], $this->codes($role));

        // A permission the role already had may stay even though the editor lacks it.
        $kept = $this->roleWith('admin.access', 'settings.payment');
        $this->actingAs($manager)->put(route('admin.roles.update', $kept), [
            'name' => 'Đổi tên', 'permissions' => ['admin.access', 'settings.payment', 'orders.view'],
        ])->assertSessionHasNoErrors()->assertSessionMissing('error');
        $this->assertSame(['admin.access', 'orders.view', 'settings.payment'], $this->codes($kept));

        // Nor hands out a role with more access than theirs.
        $this->actingAs($manager)->post(route('admin.staff.store'), [
            'name' => 'Nhân viên mới', 'email' => 'moi@example.com', 'role_id' => Role::query()->where('code', 'store-manager')->value('id'),
            'status' => 'active', 'password' => 'mat-khau-123',
        ])->assertSessionHas('error');
        $this->assertDatabaseMissing('users', ['email' => 'moi@example.com']);
    }

    public function test_system_roles_and_roles_in_use_are_protected(): void
    {
        $admin = User::factory()->admin()->create();
        $adminRole = Role::query()->create(['name' => 'Quản trị hệ thống', 'code' => 'admin']);
        $adminRole->permissions()->sync(Permission::query()->pluck('id'));

        $this->actingAs($admin)->put(route('admin.roles.update', $adminRole), ['name' => 'Quản trị hệ thống', 'permissions' => ['admin.access']])
            ->assertSessionHas('error');
        $this->assertSame(Permission::query()->count(), $adminRole->permissions()->count());
        $this->actingAs($admin)->delete(route('admin.roles.destroy', $adminRole))->assertSessionHas('error');

        $used = $this->staffIn('sales')->role;
        $this->actingAs($admin)->delete(route('admin.roles.destroy', $used))->assertSessionHas('error');

        $unused = $this->roleWith('admin.access');
        $this->actingAs($admin)->delete(route('admin.roles.destroy', $unused))->assertRedirect(route('admin.roles.index'));
        $this->assertModelMissing($unused);
        $this->assertTrue(AuditLog::query()->where('action', 'role.deleted')->exists());
    }

    public function test_you_cannot_lock_yourself_out(): void
    {
        $manager = $this->staffWith('admin.access', 'roles.manage', 'staff.manage');
        $ownRole = $manager->role;

        $this->actingAs($manager)->put(route('admin.roles.update', $ownRole), ['name' => $ownRole->name, 'permissions' => ['admin.access', 'staff.manage']])
            ->assertSessionHas('error');
        $this->actingAs($manager)->put(route('admin.staff.update', $manager), [
            'name' => $manager->name, 'email' => $manager->email, 'role_id' => $ownRole->id, 'status' => 'locked',
        ])->assertSessionHas('error');
        $this->assertTrue($manager->fresh()->isActive());

        // The last account holding roles.manage cannot be locked by someone else either.
        $staffManager = $this->staffWith('admin.access', 'staff.manage');
        $this->actingAs($staffManager)->put(route('admin.staff.update', $manager), [
            'name' => $manager->name, 'email' => $manager->email, 'role_id' => $ownRole->id, 'status' => 'locked',
        ])->assertSessionHas('error');
        $this->assertTrue($manager->fresh()->isActive());
    }

    public function test_staff_accounts_are_created_signed_in_and_locked(): void
    {
        $admin = User::factory()->admin()->create();
        $sales = Role::query()->where('code', 'sales')->sole();

        $this->actingAs($admin)->post(route('admin.staff.store'), [
            'name' => 'Lan Bán Hàng', 'email' => 'lan@example.com', 'phone' => '0901 234 567',
            'role_id' => $sales->id, 'status' => 'active', 'password' => 'mat-khau-123',
        ])->assertRedirect(route('admin.staff.index'));
        $lan = User::query()->where('email', 'lan@example.com')->sole();
        $this->assertTrue($lan->hasPermission('orders.view'));
        $this->assertNotNull($lan->email_verified_at);
        $this->assertStringNotContainsString('mat-khau-123', json_encode(AuditLog::query()->where('action', 'staff.created')->sole()->toArray()));

        $this->actingAs($admin)->get(route('admin.staff.index', ['role' => $sales->id]))->assertOk()->assertSee('Lan Bán Hàng');

        $this->actingAs($admin)->put(route('admin.staff.update', $lan), [
            'name' => 'Lan Bán Hàng', 'email' => 'lan@example.com', 'role_id' => $sales->id, 'status' => 'locked',
        ])->assertSessionMissing('error');
        $this->assertFalse($lan->fresh()->hasPermission('orders.view'));
        $this->assertSame(['status' => 'locked'], AuditLog::query()->where('action', 'staff.locked')->sole()->new_values);

        // Locked: no sign-in, and an open session ends on its next admin page.
        auth()->logout();
        $this->post(route('login'), ['email' => 'lan@example.com', 'password' => 'mat-khau-123'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->actingAs($lan->fresh())->get(route('admin.orders.index'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_customers_are_not_staff_and_the_screens_need_their_permissions(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create();

        $this->actingAs($admin)->get(route('admin.staff.index'))->assertOk()->assertDontSee($customer->email);
        $this->actingAs($admin)->get(route('admin.staff.edit', $customer))->assertNotFound();
        $this->actingAs($admin)->post(route('admin.staff.store'), [
            'name' => 'Khách', 'email' => 'khach@example.com', 'role_id' => $customer->role_id, 'status' => 'active', 'password' => 'mat-khau-123',
        ])->assertSessionHas('error');

        $sales = $this->staffIn('sales');
        $this->actingAs($sales)->get(route('admin.staff.index'))->assertForbidden();
        $this->actingAs($sales)->get(route('admin.roles.index'))->assertForbidden();
    }
}
