<?php

namespace Tests\Feature;

use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierCrudTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_customer_cannot_access_supplier_index(): void
    {
        $this->actingAs(User::factory()->create())->get(route('admin.suppliers.index'))->assertForbidden();
    }

    public function test_admin_can_view_supplier_list(): void
    {
        $supplier = Supplier::factory()->create(['name' => 'ABC Trading']);

        $this->actingAs($this->admin())
            ->get(route('admin.suppliers.index'))
            ->assertOk()
            ->assertSee('ABC Trading');
    }

    public function test_admin_can_create_supplier(): void
    {
        $response = $this->actingAs($this->admin())->post(route('admin.suppliers.store'), [
            'name' => 'XYZ Fashion Co',
            'phone' => '0901234567',
            'email' => 'contact@xyz.com',
            'address' => '123 Le Loi, HCMC',
            'tax_code' => '0101234567',
            'is_active' => '1',
        ]);

        $this->assertDatabaseHas('suppliers', [
            'name' => 'XYZ Fashion Co',
            'phone' => '0901234567',
            'email' => 'contact@xyz.com',
            'tax_code' => '0101234567',
        ]);

        $response->assertRedirect(route('admin.suppliers.index'));
    }

    public function test_supplier_requires_unique_tax_code(): void
    {
        Supplier::factory()->create(['tax_code' => '0109999999']);

        $response = $this->actingAs($this->admin())->post(route('admin.suppliers.store'), [
            'name' => 'Another Supplier',
            'phone' => '0909999999',
            'address' => '456 Nguyen Hue',
            'tax_code' => '0109999999',
            'is_active' => '1',
        ]);

        $response->assertSessionHasErrors(['tax_code']);
    }

    public function test_admin_can_update_supplier(): void
    {
        $supplier = Supplier::factory()->create();

        $response = $this->actingAs($this->admin())->put(route('admin.suppliers.update', $supplier), [
            'name' => $supplier->name,
            'phone' => '0911111111',
            'address' => '789 Ba Cong, HCMC',
            'is_active' => '0',
        ]);

        $this->assertDatabaseHas('suppliers', [
            'id' => $supplier->id,
            'phone' => '0911111111',
            'is_active' => false,
        ]);

        $response->assertRedirect(route('admin.suppliers.index'));
    }

    public function test_admin_can_delete_supplier(): void
    {
        $supplier = Supplier::factory()->create();

        $response = $this->actingAs($this->admin())->delete(route('admin.suppliers.destroy', $supplier));

        $response->assertRedirect(route('admin.suppliers.index'));
        $this->assertDatabaseMissing('suppliers', ['id' => $supplier->id]);
    }
}
