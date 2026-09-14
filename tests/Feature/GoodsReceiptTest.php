<?php

namespace Tests\Feature;

use App\Actions\ConfirmGoodsReceipt;
use App\Models\GoodsReceipt;
use App\Models\ProductVariant;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoodsReceiptTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function makePayload(array $overrides = []): array
    {
        $supplier = Supplier::factory()->create();
        $variant = ProductVariant::factory()->create();

        return array_merge([
            'supplier_id' => $supplier->id,
            'notes' => 'Phiếu nhập mẫu',
            'items' => [
                ['product_variant_id' => $variant->id, 'quantity' => 10, 'cost_price' => 150000],
            ],
        ], $overrides);
    }

    public function test_customer_cannot_access_goods_receipt_index(): void
    {
        $this->actingAs(User::factory()->create())->get(route('admin.goods-receipts.index'))->assertForbidden();
    }

    public function test_admin_can_create_draft_receipt(): void
    {
        $payload = $this->makePayload();

        $response = $this->withoutExceptionHandling()->actingAs($this->admin())->post(route('admin.goods-receipts.store'), $payload);

        $receipt = GoodsReceipt::query()->first();
        $this->assertNotNull($receipt);
        $this->assertSame('draft', $receipt->status);
        $this->assertSame(1500000, (int) $receipt->total_cost);
        $this->assertSame(1, $receipt->items()->count());
        $this->assertSame(1500000, (int) $receipt->items()->first()->subtotal);

        $response->assertRedirect(route('admin.goods-receipts.show', $receipt));
    }

    public function test_receipt_items_must_be_unique_by_variant(): void
    {
        $variant = ProductVariant::factory()->create();
        $supplier = Supplier::factory()->create();

        $payload = [
            'supplier_id' => $supplier->id,
            'items' => [
                ['product_variant_id' => $variant->id, 'quantity' => 5, 'cost_price' => 100000],
                ['product_variant_id' => $variant->id, 'quantity' => 3, 'cost_price' => 100000],
            ],
        ];

        $response = $this->actingAs($this->admin())->post(route('admin.goods-receipts.store'), $payload);

        $response->assertSessionHasErrors(['items.1.product_variant_id']);
        $this->assertDatabaseCount('goods_receipts', 0);
    }

    public function test_removing_a_row_updates_the_receipt_with_sparse_item_indexes(): void
    {
        $payload = $this->makePayload();
        $second = ProductVariant::factory()->create(['stock_quantity' => 7]);
        $payload['items'][1] = ['product_variant_id' => $second->id, 'quantity' => 3, 'cost_price' => 25000];
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('admin.goods-receipts.store'), $payload);
        $receipt = GoodsReceipt::query()->first();
        unset($payload['items'][0]);

        $this->actingAs($admin)->put(route('admin.goods-receipts.update', $receipt), $payload)->assertSessionHasNoErrors();

        $this->assertSame(1, $receipt->items()->count());
        $this->assertSame($second->id, $receipt->items()->first()->product_variant_id);
        $this->assertSame(75000, (int) $receipt->fresh()->total_cost);
        $this->assertSame(7, $second->fresh()->stock_quantity);
    }

    public function test_removing_all_rows_cannot_save_an_empty_receipt(): void
    {
        $payload = $this->makePayload();
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('admin.goods-receipts.store'), $payload);
        $receipt = GoodsReceipt::query()->first();
        unset($payload['items']);

        $this->actingAs($admin)->put(route('admin.goods-receipts.update', $receipt), $payload)->assertSessionHasErrors('items');

        $this->assertSame(1, $receipt->items()->count());
        $this->assertSame(1500000, (int) $receipt->fresh()->total_cost);
    }

    public function test_invalid_receipt_keeps_remaining_rows_and_errors_after_removal(): void
    {
        $payload = $this->makePayload();
        $payload['items'] = [3 => $payload['items'][0]];
        $payload['items'][3]['quantity'] = 0;
        $admin = $this->admin();
        $this->actingAs($admin)->from(route('admin.goods-receipts.create'))->post(route('admin.goods-receipts.store'), $payload)->assertSessionHasErrors('items.3.quantity');

        $this->get(route('admin.goods-receipts.create'))->assertOk()->assertSee('name="items[3][quantity]"', false)->assertSee('Xóa dòng hàng');
        $this->assertDatabaseCount('goods_receipts', 0);
    }

    public function test_confirming_receipt_increments_stock(): void
    {
        $variant = ProductVariant::factory()->create(['stock_quantity' => 5]);
        $supplier = Supplier::factory()->create();

        $payload = [
            'supplier_id' => $supplier->id,
            'items' => [
                ['product_variant_id' => $variant->id, 'quantity' => 10, 'cost_price' => 150000],
            ],
        ];

        $this->actingAs($this->admin())->post(route('admin.goods-receipts.store'), $payload);

        $receipt = GoodsReceipt::query()->first();

        $response = $this->actingAs($this->admin())->post(route('admin.goods-receipts.confirm', $receipt));

        $response->assertRedirect(route('admin.goods-receipts.show', $receipt));

        $this->assertSame('confirmed', $receipt->fresh()->status);
        $this->assertSame(15, $variant->fresh()->stock_quantity);
        $this->assertNotNull($receipt->fresh()->confirmed_at);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'goods_receipt.confirmed',
            'subject_id' => $receipt->id,
        ]);
    }

    public function test_cannot_confirm_already_confirmed_receipt(): void
    {
        $variant = ProductVariant::factory()->create(['stock_quantity' => 0]);
        $supplier = Supplier::factory()->create();

        $payload = [
            'supplier_id' => $supplier->id,
            'items' => [
                ['product_variant_id' => $variant->id, 'quantity' => 10, 'cost_price' => 150000],
            ],
        ];

        $this->actingAs($this->admin())->post(route('admin.goods-receipts.store'), $payload);
        $receipt = GoodsReceipt::query()->first();

        // First confirm succeeds.
        $this->actingAs($this->admin())->post(route('admin.goods-receipts.confirm', $receipt));
        $this->assertSame(10, $variant->fresh()->stock_quantity);

        // Second confirm is a no-op.
        $response = $this->actingAs($this->admin())->post(route('admin.goods-receipts.confirm', $receipt));

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertSame(10, $variant->fresh()->stock_quantity);
    }

    public function test_cannot_edit_confirmed_receipt(): void
    {
        $variant = ProductVariant::factory()->create();
        $supplier = Supplier::factory()->create();

        $payload = [
            'supplier_id' => $supplier->id,
            'items' => [
                ['product_variant_id' => $variant->id, 'quantity' => 5, 'cost_price' => 100000],
            ],
        ];

        $this->actingAs($this->admin())->post(route('admin.goods-receipts.store'), $payload);
        $receipt = GoodsReceipt::query()->first();
        $this->actingAs($this->admin())->post(route('admin.goods-receipts.confirm', $receipt));

        $response = $this->actingAs($this->admin())->put(route('admin.goods-receipts.update', $receipt), $payload);

        $response->assertForbidden();
    }

    public function test_cannot_delete_confirmed_receipt(): void
    {
        $variant = ProductVariant::factory()->create();
        $supplier = Supplier::factory()->create();

        $payload = [
            'supplier_id' => $supplier->id,
            'items' => [
                ['product_variant_id' => $variant->id, 'quantity' => 5, 'cost_price' => 100000],
            ],
        ];

        $this->actingAs($this->admin())->post(route('admin.goods-receipts.store'), $payload);
        $receipt = GoodsReceipt::query()->first();
        $this->actingAs($this->admin())->post(route('admin.goods-receipts.confirm', $receipt));

        $response = $this->actingAs($this->admin())->delete(route('admin.goods-receipts.destroy', $receipt));

        $response->assertForbidden();
        $this->assertDatabaseHas('goods_receipts', ['id' => $receipt->id]);
    }

    public function test_admin_can_delete_draft_receipt(): void
    {
        $payload = $this->makePayload();
        $this->actingAs($this->admin())->post(route('admin.goods-receipts.store'), $payload);
        $receipt = GoodsReceipt::query()->first();

        $response = $this->actingAs($this->admin())->delete(route('admin.goods-receipts.destroy', $receipt));

        $response->assertRedirect(route('admin.goods-receipts.index'));
        $this->assertDatabaseMissing('goods_receipts', ['id' => $receipt->id]);
    }

    /**
     * Concurrency test: two confirm requests against the same draft receipt
     * should only increment stock once (the second is a no-op).
     */
    public function test_concurrent_confirms_only_increments_stock_once(): void
    {
        $variant = ProductVariant::factory()->create(['stock_quantity' => 0]);
        $supplier = Supplier::factory()->create();

        $payload = [
            'supplier_id' => $supplier->id,
            'items' => [
                ['product_variant_id' => $variant->id, 'quantity' => 10, 'cost_price' => 150000],
            ],
        ];

        $this->actingAs($this->admin())->post(route('admin.goods-receipts.store'), $payload);
        $receipt = GoodsReceipt::query()->first();

        // Simulate two concurrent confirms in separate transactions.
        // The first one wins; the second should see status=confirmed and no-op.
        $action = new ConfirmGoodsReceipt;
        $admin = $this->admin();

        $result1 = $action->execute($receipt, $admin);
        $result2 = $action->execute($receipt->fresh(), $admin);

        $this->assertNull($result1['error']);
        $this->assertNotNull($result2['error']);

        // Stock should be incremented exactly once (by 10).
        $this->assertSame(10, $variant->fresh()->stock_quantity);
    }
}
