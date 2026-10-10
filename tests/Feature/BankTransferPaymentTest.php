<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\AuditLog;
use App\Models\Cart;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class BankTransferPaymentTest extends TestCase
{
    use RefreshDatabase;

    private const API_KEY = 'sepay-test-key';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'store.shipping_fee' => 30000,
            'store.bank_transfer.bank_id' => 'VCB',
            'store.bank_transfer.account_number' => '0123456789',
            'store.bank_transfer.account_name' => 'FASHION STORE',
            'services.sepay.webhook_api_key' => self::API_KEY,
        ]);
    }

    private function bankTransferOrder(array $attributes = []): Order
    {
        return Order::factory()->create(array_merge([
            'order_number' => 'DH261010ABC123',
            'payment_method' => 'bank_transfer',
            'grand_total' => 330000,
        ], $attributes));
    }

    /**
     * @return array<string, mixed>
     */
    private function sepayPayload(array $overrides = []): array
    {
        return array_merge([
            'id' => 92704,
            'gateway' => 'Vietcombank',
            'transactionDate' => '2026-10-10 14:02:37',
            'accountNumber' => '0123456789',
            'code' => null,
            'content' => 'NGUYEN VAN A chuyen tien DH261010ABC123 FT26283',
            'transferType' => 'in',
            'transferAmount' => 330000,
            'accumulated' => 19077000,
            'subAccount' => null,
            'referenceCode' => 'MBVCB.3278907687',
            'description' => '',
        ], $overrides);
    }

    private function postWebhook(array $payload, ?string $apiKey = self::API_KEY): TestResponse
    {
        $headers = $apiKey === null ? [] : ['Authorization' => 'Apikey '.$apiKey];

        return $this->postJson(route('webhooks.sepay'), $payload, $headers);
    }

    public function test_checkout_offers_bank_transfer_and_order_page_shows_the_qr(): void
    {
        $customer = User::factory()->create();
        $address = Address::factory()->for($customer)->default()->create();
        $cart = Cart::query()->create(['user_id' => $customer->id, 'status' => 'active']);
        $cart->items()->create([
            'product_variant_id' => ProductVariant::factory()->create(['price' => 300000, 'stock_quantity' => 5])->id,
            'quantity' => 1,
        ]);

        $this->actingAs($customer)->get(route('checkout.create'))->assertSee('value="bank_transfer"', false);

        $this->actingAs($customer)->post(route('checkout.store'), [
            'address_id' => $address->id,
            'payment_method' => 'bank_transfer',
        ])->assertSessionHasNoErrors();

        $order = Order::query()->sole();
        $this->assertSame('bank_transfer', $order->payment_method);
        $this->assertSame('unpaid', $order->payment_status);

        $this->actingAs($customer)->get(route('orders.show', $order))
            ->assertOk()
            ->assertSee('Quét mã QR để chuyển khoản')
            ->assertSee('https://img.vietqr.io/image/VCB-0123456789-compact2.png?amount=330000&amp;addInfo='.$order->order_number, false);
    }

    public function test_bank_transfer_is_refused_while_no_account_is_configured(): void
    {
        config(['store.bank_transfer.account_number' => null]);
        $customer = User::factory()->create();
        $address = Address::factory()->for($customer)->default()->create();
        Cart::query()->create(['user_id' => $customer->id, 'status' => 'active'])->items()->create([
            'product_variant_id' => ProductVariant::factory()->create(['stock_quantity' => 5])->id,
            'quantity' => 1,
        ]);

        $this->actingAs($customer)->get(route('checkout.create'))->assertDontSee('value="bank_transfer"', false);
        $this->actingAs($customer)->post(route('checkout.store'), ['address_id' => $address->id, 'payment_method' => 'bank_transfer'])
            ->assertSessionHasErrors('payment_method');
    }

    public function test_webhook_without_or_with_a_wrong_api_key_is_rejected_and_changes_nothing(): void
    {
        $order = $this->bankTransferOrder();

        $this->postWebhook($this->sepayPayload(), apiKey: null)->assertUnauthorized();
        $this->postWebhook($this->sepayPayload(), apiKey: 'wrong-key')->assertUnauthorized();

        config(['services.sepay.webhook_api_key' => null]);
        $this->postWebhook($this->sepayPayload(), apiKey: '')->assertUnauthorized();

        $this->assertSame('unpaid', $order->fresh()->payment_status);
        $this->assertSame(0, AuditLog::query()->count());
    }

    public function test_matching_order_number_and_amount_marks_the_order_paid(): void
    {
        $order = $this->bankTransferOrder();

        $this->postWebhook($this->sepayPayload())
            ->assertOk()
            ->assertJson(['success' => true, 'result' => 'matched']);

        $order->refresh();
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('SEPAY-92704', $order->transaction_code);
        $this->assertNull($order->payment_reviewed_by);
        $this->assertNotNull($order->payment_reviewed_at);
        $this->assertSame(1, AuditLog::query()->where('action', 'payment.sepay_matched')->count());
    }

    public function test_order_number_is_found_in_a_lower_cased_memo_or_in_sepays_code(): void
    {
        $first = $this->bankTransferOrder();
        $second = $this->bankTransferOrder(['order_number' => 'DH261010XYZ789']);

        $this->postWebhook($this->sepayPayload(['content' => 'ck dh261010abc123']))->assertJson(['result' => 'matched']);
        $this->postWebhook($this->sepayPayload(['id' => 92705, 'content' => 'chuyen tien', 'code' => 'DH261010XYZ789']))
            ->assertJson(['result' => 'matched']);

        $this->assertSame('paid', $first->fresh()->payment_status);
        $this->assertSame('paid', $second->fresh()->payment_status);
    }

    public function test_wrong_amount_or_unknown_order_only_writes_an_audit_log(): void
    {
        $order = $this->bankTransferOrder();

        $this->postWebhook($this->sepayPayload(['transferAmount' => 300000]))
            ->assertOk()
            ->assertJson(['success' => true, 'result' => 'unmatched']);
        $this->postWebhook($this->sepayPayload(['id' => 92705, 'content' => 'chuyen tien khong ghi ma']))
            ->assertJson(['result' => 'unmatched']);

        $this->assertSame('unpaid', $order->fresh()->payment_status);
        $logs = AuditLog::query()->where('action', 'payment.sepay_unmatched')->orderBy('id')->get();
        $this->assertSame(['amount_mismatch', 'order_not_found'], $logs->pluck('new_values.reason')->all());
        $this->assertSame(92704, $logs->first()->new_values['payload']['id']);
    }

    public function test_cod_or_cancelled_orders_are_never_marked_paid_by_a_transfer(): void
    {
        $cod = $this->bankTransferOrder(['payment_method' => 'cod']);
        $cancelled = $this->bankTransferOrder(['order_number' => 'DH261010CCC111', 'status' => 'cancelled']);

        $this->postWebhook($this->sepayPayload())->assertJson(['result' => 'unmatched']);
        $this->postWebhook($this->sepayPayload(['id' => 92705, 'content' => 'DH261010CCC111']))->assertJson(['result' => 'unmatched']);

        $this->assertSame('unpaid', $cod->fresh()->payment_status);
        $this->assertSame('unpaid', $cancelled->fresh()->payment_status);
    }

    public function test_sepay_retrying_the_same_transfer_is_recorded_once(): void
    {
        $order = $this->bankTransferOrder();

        $this->postWebhook($this->sepayPayload())->assertJson(['result' => 'matched']);
        $this->postWebhook($this->sepayPayload())->assertOk()->assertJson(['result' => 'duplicate']);

        $this->assertSame(1, AuditLog::query()->count());
        $this->assertSame('SEPAY-92704', $order->fresh()->transaction_code);
    }

    public function test_a_second_different_transfer_to_a_paid_order_is_flagged(): void
    {
        $this->bankTransferOrder();

        $this->postWebhook($this->sepayPayload())->assertJson(['result' => 'matched']);
        $this->postWebhook($this->sepayPayload(['id' => 99999]))->assertJson(['result' => 'unmatched']);

        $this->assertSame('order_already_paid', AuditLog::query()->where('action', 'payment.sepay_unmatched')->sole()->new_values['reason']);
    }

    public function test_outgoing_transfers_are_ignored(): void
    {
        $order = $this->bankTransferOrder();

        $this->postWebhook($this->sepayPayload(['transferType' => 'out']))->assertJson(['result' => 'ignored']);

        $this->assertSame('unpaid', $order->fresh()->payment_status);
        $this->assertSame(0, AuditLog::query()->count());
    }

    public function test_malformed_payload_gets_a_422(): void
    {
        $this->postWebhook(['transferType' => 'in'])->assertUnprocessable();
    }

    public function test_payment_status_endpoint_is_owner_only(): void
    {
        $order = $this->bankTransferOrder();

        $this->actingAs($order->user)->getJson(route('orders.payment-status', $order))
            ->assertOk()
            ->assertExactJson(['payment_status' => 'unpaid']);
        $this->actingAs(User::factory()->create())->getJson(route('orders.payment-status', $order))->assertForbidden();
    }

    public function test_customer_cannot_cancel_an_order_that_is_already_paid(): void
    {
        $order = $this->bankTransferOrder(['payment_status' => 'paid']);

        $this->actingAs($order->user)->patch(route('orders.cancel', $order))
            ->assertSessionHas('error', fn (string $message): bool => str_contains($message, 'liên hệ cửa hàng'));

        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_staff_can_still_cancel_a_paid_order_and_see_the_refund_warning(): void
    {
        $admin = User::factory()->admin()->create();
        $order = $this->bankTransferOrder(['payment_status' => 'paid']);

        $this->actingAs($admin)->patch(route('admin.orders.status', $order), ['status' => 'cancelled', 'note' => 'Hết hàng']);

        $this->assertSame('cancelled', $order->fresh()->status);
        $this->actingAs($admin)->get(route('admin.orders.show', $order))->assertSee('cần hoàn tiền cho khách');
    }
}
