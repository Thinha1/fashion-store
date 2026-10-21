<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\Order;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogPageTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create(['name' => 'Quản trị viên']);
    }

    public function test_only_users_with_audit_view_can_open_the_log(): void
    {
        $staff = User::factory()->create(['role_id' => Role::factory()->create(['code' => 'staff'])->id]);
        $staff->role->permissions()->attach(Permission::query()->where('code', 'admin.access')->value('id'));

        $this->actingAs($staff)->get(route('admin.audit-logs.index'))->assertForbidden();
        $this->actingAs($this->admin)->get(route('admin.audit-logs.index'))->assertOk()->assertSee('Nhật ký hệ thống');
        $this->assertTrue(Permission::query()->where('code', 'audit.view')->exists());
    }

    public function test_entries_are_listed_newest_first_with_readable_labels(): void
    {
        $brand = Brand::factory()->create();
        $logger = app(AuditLogger::class);
        $logger->record('brand.updated', $brand, ['name' => 'Cũ'], ['name' => 'Mới'], actor: $this->admin);
        $logger->record('payment.sepay_unmatched', Order::class, null, ['amount' => 100000]);

        $rows = $this->rows($this->actingAs($this->admin)->get(route('admin.audit-logs.index'))->assertOk()->getContent());

        $this->assertSame(['Hệ thống', 'SePay: chuyển khoản không khớp', 'Đơn hàng'], array_slice($rows[0], 1, 3));
        $this->assertSame(['Quản trị viên '.$this->admin->email, 'Sửa thương hiệu', 'Thương hiệu #'.$brand->id], array_slice($rows[1], 1, 3));
    }

    public function test_entries_can_be_filtered(): void
    {
        $brand = Brand::factory()->create();
        $logger = app(AuditLogger::class);
        $logger->record('brand.updated', $brand, ['name' => 'Cũ'], ['name' => 'Mới'], actor: $this->admin);
        $logger->record('payment.sepay_unmatched', Order::class, null, ['amount' => 100000]);
        AuditLog::query()->where('action', 'brand.updated')->update(['created_at' => now()->subDays(10)]);

        $actions = fn (array $query): array => array_column($this->rows($this->actingAs($this->admin)->get(route('admin.audit-logs.index', $query))->getContent()), 2);

        $this->assertSame(['SePay: chuyển khoản không khớp'], $actions(['actor' => 'system']));
        $this->assertSame(['Sửa thương hiệu'], $actions(['action' => 'brand.updated', 'actor' => $this->admin->id]));
        $this->assertSame(['SePay: chuyển khoản không khớp'], $actions(['from' => now()->subDay()->toDateString()]));
        $this->assertSame(['Sửa thương hiệu'], $actions(['to' => now()->subDays(5)->toDateString()]));
    }

    public function test_an_entry_shows_its_values_before_and_after(): void
    {
        $brand = Brand::factory()->create();
        $log = app(AuditLogger::class)->record('brand.updated', $brand, ['name' => 'Tên cũ', 'is_active' => true], ['name' => 'Tên mới', 'is_active' => true], actor: $this->admin);

        $this->actingAs($this->admin)->get(route('admin.audit-logs.show', $log))
            ->assertOk()
            ->assertSeeInOrder(['Sửa thương hiệu', 'Quản trị viên', 'name', 'Tên cũ', 'Tên mới', 'is_active', 'Có', 'Có'])
            ->assertSee(route('admin.brands.edit', $brand), false);
    }

    /**
     * Text of each cell, per table row of the log list.
     *
     * @return list<list<string>>
     */
    private function rows(string $html): array
    {
        $body = substr($html, (int) strpos($html, '<tbody'));
        preg_match_all('/<tr[^>]*>(.*?)<\/tr>/s', $body, $rows);

        return array_values(array_filter(array_map(function (string $row): array {
            preg_match_all('/<td[^>]*>(.*?)<\/td>/s', $row, $cells);

            return array_map(fn (string $cell): string => trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($cell)))), $cells[1]);
        }, $rows[1]), fn (array $cells): bool => count($cells) > 1));
    }
}
