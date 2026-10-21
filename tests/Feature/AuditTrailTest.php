<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\GoodsReceipt;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditTrailTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_record_in_admin_logs_who_did_it_and_from_where(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->withHeader('User-Agent', 'AuditTest/1.0')
            ->post(route('admin.brands.store'), ['name' => 'Coolmate', 'slug' => 'coolmate', 'is_active' => '1']);

        $log = AuditLog::query()->where('action', 'brand.created')->sole();
        $this->assertSame($admin->id, $log->actor_id);
        $this->assertSame((new Brand)->getMorphClass(), $log->subject_type);
        $this->assertSame('Coolmate', $log->new_values['name']);
        $this->assertArrayNotHasKey('created_by', $log->new_values);
        $this->assertSame('127.0.0.1', $log->ip_address);
        $this->assertSame('AuditTest/1.0', $log->user_agent);
    }

    public function test_an_update_logs_only_the_changed_fields_before_and_after(): void
    {
        $product = Product::factory()->create(['name' => 'Áo cũ', 'base_price' => 199000]);
        $product->update(['name' => 'Áo mới', 'base_price' => 249000, 'description' => $product->description]);
        $this->assertSame(0, AuditLog::query()->count(), 'changes outside admin routes are not logged');

        $this->actingAs(User::factory()->admin()->create())
            ->patch(route('admin.products.featured', $product), ['is_featured' => true]);

        $log = AuditLog::query()->where('action', 'product.updated')->sole();
        $this->assertSame(['is_featured' => false], $log->old_values);
        $this->assertSame(['is_featured' => true], $log->new_values);
    }

    public function test_deleting_logs_the_values_that_were_removed(): void
    {
        $supplier = Supplier::factory()->create(['name' => 'NCC Sài Gòn']);

        $this->actingAs(User::factory()->admin()->create())->delete(route('admin.suppliers.destroy', $supplier));

        $log = AuditLog::query()->where('action', 'supplier.deleted')->sole();
        $this->assertSame('NCC Sài Gòn', $log->old_values['name']);
        $this->assertNull($log->new_values);
    }

    public function test_confirming_a_goods_receipt_logs_one_entry_not_one_per_variant(): void
    {
        $admin = User::factory()->admin()->create();
        $variant = ProductVariant::factory()->create(['stock_quantity' => 5]);
        $this->actingAs($admin)->post(route('admin.goods-receipts.store'), [
            'supplier_id' => Supplier::factory()->create()->id,
            'items' => [['product_variant_id' => $variant->id, 'quantity' => 10, 'cost_price' => 150000]],
        ]);
        AuditLog::query()->delete();

        $this->actingAs($admin)->post(route('admin.goods-receipts.confirm', GoodsReceipt::query()->sole()));

        $this->assertSame(15, $variant->fresh()->stock_quantity);
        $this->assertSame(['goods_receipt.confirmed'], AuditLog::query()->pluck('action')->all());
    }

    public function test_staff_sign_ins_are_logged_but_customer_sign_ins_are_not(): void
    {
        $admin = User::factory()->admin()->create(['password' => 'password']);
        $customer = User::factory()->create(['password' => 'password']);

        $this->post(route('login'), ['email' => $customer->email, 'password' => 'password']);
        $this->post(route('logout'));
        $this->post(route('login'), ['email' => $admin->email, 'password' => 'password']);

        $log = AuditLog::query()->sole();
        $this->assertSame('auth.login', $log->action);
        $this->assertSame($admin->id, $log->actor_id);
        $this->assertSame($admin->id, $log->subject_id);
    }
}
