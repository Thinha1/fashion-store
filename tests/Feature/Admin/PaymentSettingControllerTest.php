<?php

namespace Tests\Feature\Admin;

use App\Models\Address;
use App\Models\AuditLog;
use App\Models\Cart;
use App\Models\Order;
use App\Models\PaymentSetting;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Payments\BankTransfer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PaymentSettingControllerTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'a1b2c3d4e5f6a7b8c9d0e1f2';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'store.bank_transfer.bank_id' => null,
            'store.bank_transfer.account_number' => null,
            'store.bank_transfer.account_name' => null,
            'services.sepay.webhook_api_key' => null,
        ]);
    }

    private function save(array $input): TestResponse
    {
        return $this->actingAs(User::factory()->admin()->create())
            ->put(route('admin.settings.payment.update'), $input);
    }

    public function test_only_settings_managers_can_open_or_save_the_page(): void
    {
        $staff = User::factory()->create();
        $this->actingAs($staff)->get(route('admin.settings.payment.edit'))->assertForbidden();
        $this->actingAs($staff)->put(route('admin.settings.payment.update'), ['bank_id' => 'VCB'])->assertForbidden();

        $this->actingAs(User::factory()->admin()->create())->get(route('admin.settings.payment.edit'))
            ->assertOk()
            ->assertSee('Cấu hình thanh toán QR')
            ->assertSee(route('webhooks.sepay'))
            ->assertSee('đang <strong>ẩn</strong> lựa chọn chuyển khoản', false);
        $this->assertDatabaseCount('payment_settings', 0);
    }

    public function test_saving_stores_the_account_and_an_encrypted_key_and_turns_bank_transfer_on(): void
    {
        $this->save(['bank_id' => 'mb', 'account_number' => '0123456789', 'account_name' => 'nguyễn  van a', 'sepay_webhook_api_key' => self::KEY])
            ->assertRedirect(route('admin.settings.payment.edit'))
            ->assertSessionHasNoErrors();

        $setting = PaymentSetting::query()->sole();
        $this->assertSame(['MB', '0123456789', 'NGUYỄN VAN A'], [$setting->bank_id, $setting->account_number, $setting->account_name]);
        $this->assertSame(self::KEY, $setting->sepay_webhook_api_key);
        $this->assertNotSame(self::KEY, DB::table('payment_settings')->value('sepay_webhook_api_key'));

        $bankTransfer = app(BankTransfer::class);
        $this->assertTrue($bankTransfer->isEnabled());
        $this->assertSame(self::KEY, $bankTransfer->webhookApiKey());
    }

    public function test_the_page_never_shows_the_saved_key(): void
    {
        $this->save(['sepay_webhook_api_key' => self::KEY]);

        $this->actingAs(User::factory()->admin()->create())->get(route('admin.settings.payment.edit'))
            ->assertOk()
            ->assertDontSee(self::KEY)
            ->assertSee('••••••••e1f2');
    }

    public function test_a_blank_key_keeps_the_saved_one_and_the_clear_box_removes_it(): void
    {
        $this->save(['bank_id' => 'VCB', 'sepay_webhook_api_key' => self::KEY]);
        $this->save(['bank_id' => 'ACB', 'sepay_webhook_api_key' => '']);
        $this->assertSame(['ACB', self::KEY], [PaymentSetting::current()->bank_id, PaymentSetting::current()->sepay_webhook_api_key]);

        $this->save(['bank_id' => 'ACB', 'clear_sepay_webhook_api_key' => '1']);
        $this->assertNull(PaymentSetting::current()->sepay_webhook_api_key);
        $this->assertDatabaseCount('payment_settings', 1);
    }

    public function test_bad_input_is_rejected(): void
    {
        $this->save(['bank_id' => 'V C B', 'account_number' => '01-234', 'sepay_webhook_api_key' => 'short'])
            ->assertSessionHasErrors(['bank_id', 'account_number', 'sepay_webhook_api_key']);
        $this->assertDatabaseCount('payment_settings', 0);
    }

    public function test_an_empty_field_falls_back_to_env(): void
    {
        config(['store.bank_transfer.bank_id' => 'VCB', 'store.bank_transfer.account_number' => '999', 'services.sepay.webhook_api_key' => 'env-key']);
        $this->save(['account_number' => '0123456789']);

        $bankTransfer = app(BankTransfer::class);
        $this->assertSame(['VCB', '0123456789', 'env-key'], [$bankTransfer->bankId(), $bankTransfer->accountNumber(), $bankTransfer->webhookApiKey()]);
    }

    public function test_saving_is_audited_without_the_key(): void
    {
        $this->save(['bank_id' => 'VCB', 'account_number' => '0123456789', 'sepay_webhook_api_key' => self::KEY]);

        $log = AuditLog::query()->where('action', 'payment_settings.updated')->sole();
        // assertEquals: MySQL's JSON column does not keep key order.
        $this->assertEquals(['bank_id' => 'VCB', 'account_number' => '0123456789', 'account_name' => null, 'webhook_url' => null, 'api_key_changed' => true], $log->new_values);
        $this->assertStringNotContainsString(self::KEY, json_encode($log->toArray()));
    }

    public function test_the_webhook_and_checkout_use_the_saved_settings(): void
    {
        $this->save(['bank_id' => 'VCB', 'account_number' => '0123456789', 'account_name' => 'FASHION STORE', 'sepay_webhook_api_key' => self::KEY]);
        $order = Order::factory()->create(['order_number' => 'DH261022ABC123', 'payment_method' => 'bank_transfer', 'grand_total' => 330000]);
        $payload = ['id' => 1, 'transferType' => 'in', 'transferAmount' => 330000, 'content' => 'CK DH261022ABC123'];

        $this->postJson(route('webhooks.sepay'), $payload, ['Authorization' => 'Apikey wrong-key'])->assertUnauthorized();
        $this->postJson(route('webhooks.sepay'), $payload, ['Authorization' => 'Apikey '.self::KEY])
            ->assertOk()->assertJsonPath('result', 'matched');
        $this->assertSame('paid', $order->fresh()->payment_status);

        $customer = User::factory()->create();
        Address::factory()->for($customer)->default()->create();
        Cart::query()->create(['user_id' => $customer->id, 'status' => 'active'])->items()->create([
            'product_variant_id' => ProductVariant::factory()->create(['stock_quantity' => 5])->id,
            'quantity' => 1,
        ]);
        $this->actingAs($customer)->get(route('checkout.create'))->assertSee('value="bank_transfer"', false);
    }

    public function test_the_webhook_url_is_the_saved_public_one_else_the_app_route(): void
    {
        $this->assertSame(route('webhooks.sepay'), app(BankTransfer::class)->webhookUrl());

        $this->save(['webhook_url' => 'http://insecure.example.test/webhooks/sepay'])->assertSessionHasErrors('webhook_url');
        $this->save(['webhook_url' => 'https://shop.tail1.ts.net/webhooks/sepay'])->assertSessionHasNoErrors();

        $this->assertSame('https://shop.tail1.ts.net/webhooks/sepay', app(BankTransfer::class)->webhookUrl());
    }

    public function test_the_webhook_check_calls_the_saved_url_with_the_key_and_an_ignored_payload(): void
    {
        $this->save(['webhook_url' => 'https://shop.tail1.ts.net/webhooks/sepay', 'sepay_webhook_api_key' => self::KEY]);
        Http::fake(['shop.tail1.ts.net/*' => Http::response(['success' => true, 'result' => 'ignored'])]);

        $this->postJson(route('admin.settings.payment.test'))->assertOk()->assertJsonPath('ok', true);

        Http::assertSent(fn (Request $request) => $request->url() === 'https://shop.tail1.ts.net/webhooks/sepay'
            && $request->header('Authorization') === ['Apikey '.self::KEY]
            && $request['transferType'] === 'out');
    }

    public function test_the_webhook_check_explains_what_went_wrong(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $this->postJson(route('admin.settings.payment.test'))->assertJsonPath('ok', false)->assertJsonPath('message', fn (string $message) => str_contains($message, 'Chưa có API key'));
        Http::assertNothingSent();

        $this->save(['webhook_url' => 'https://shop.tail1.ts.net/webhooks/sepay', 'sepay_webhook_api_key' => self::KEY]);
        Http::fake(['shop.tail1.ts.net/*' => Http::sequence()
            ->push(['success' => false], 401)
            ->push('<html>', 404)
            ->pushFailedConnection()]);

        $messages = collect(range(1, 3))->map(fn () => $this->postJson(route('admin.settings.payment.test'))->assertJsonPath('ok', false)->json('message'));

        $this->assertStringContainsString('API key bị từ chối', $messages[0]);
        $this->assertStringContainsString('HTTP 404', $messages[1]);
        $this->assertStringContainsString('Không gọi được', $messages[2]);
    }
}
