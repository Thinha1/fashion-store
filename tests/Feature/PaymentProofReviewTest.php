<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PaymentProofReviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config([
            'store.bank_transfer.bank_id' => 'VCB',
            'store.bank_transfer.account_number' => '0123456789',
            'store.bank_transfer.account_name' => 'FASHION STORE',
            'store.bank_transfer.proof_wait_minutes' => 15,
            'store.bank_transfer.proof_disk' => 'local',
        ]);
    }

    private function transferOrder(array $attributes = []): Order
    {
        return Order::factory()->create(array_merge([
            'payment_method' => 'bank_transfer',
            'placed_at' => now()->subMinutes(20),
        ], $attributes));
    }

    private function sendProof(Order $order, ?User $as = null): TestResponse
    {
        return $this->actingAs($as ?? $order->user)->post(route('orders.payment-proof', $order), [
            'transaction_code' => 'FT26283123456',
            'receipt' => UploadedFile::fake()->image('bien-lai.jpg', 600, 1000),
        ]);
    }

    private function reviewer(): User
    {
        $role = Role::factory()->create();
        $role->permissions()->attach(Permission::query()->whereIn('code', ['admin.access', 'payments.manage'])->pluck('id'));

        return User::factory()->create(['role_id' => $role->id]);
    }

    public function test_form_appears_only_after_the_wait(): void
    {
        $fresh = $this->transferOrder(['placed_at' => now()->subMinutes(5)]);
        $waited = $this->transferOrder();

        $this->actingAs($fresh->user)->get(route('orders.show', $fresh))->assertDontSee('Gửi chứng từ')->assertSee('vẫn chưa được xác nhận');
        $this->actingAs($waited->user)->get(route('orders.show', $waited))->assertSee('Gửi chứng từ');
    }

    public function test_customer_submits_a_receipt_after_the_wait(): void
    {
        $order = $this->transferOrder();

        $this->sendProof($order)->assertRedirect(route('orders.show', $order))->assertSessionHas('status');

        $order->refresh();
        $this->assertSame('pending_review', $order->payment_status);
        $this->assertSame('FT26283123456', $order->transaction_code);
        $this->assertNotNull($order->payment_proof_submitted_at);
        Storage::disk('local')->assertExists($order->payment_proof_path);
        $this->assertStringStartsWith('payment-proofs/', $order->payment_proof_path);
        $this->assertSame(1, AuditLog::query()->where('action', 'payment.proof_submitted')->count());

        $this->actingAs($order->user)->get(route('orders.show', $order))->assertSee('Cửa hàng đang kiểm tra chứng từ');
    }

    public function test_receipt_is_refused_before_the_wait_and_the_file_is_not_kept(): void
    {
        $order = $this->transferOrder(['placed_at' => now()->subMinutes(5)]);

        $this->sendProof($order)->assertSessionHas('error');

        $this->assertSame('unpaid', $order->fresh()->payment_status);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_cod_paid_or_someone_elses_orders_take_no_receipt(): void
    {
        $cod = $this->transferOrder(['payment_method' => 'cod']);
        $paid = $this->transferOrder(['payment_status' => 'paid']);
        $foreign = $this->transferOrder();

        $this->sendProof($cod)->assertSessionHas('error');
        $this->sendProof($paid)->assertSessionHas('error');
        $this->sendProof($foreign, User::factory()->create())->assertForbidden();

        $this->assertSame('unpaid', $foreign->fresh()->payment_status);
    }

    public function test_receipt_must_be_an_image(): void
    {
        $order = $this->transferOrder();

        $this->actingAs($order->user)->post(route('orders.payment-proof', $order), [
            'transaction_code' => 'FT1',
            'receipt' => UploadedFile::fake()->create('bien-lai.pdf', 100, 'application/pdf'),
        ])->assertSessionHasErrors('receipt');
    }

    public function test_review_screens_need_the_payments_permission(): void
    {
        $order = $this->transferOrder(['payment_status' => 'pending_review']);
        $orderStaff = User::factory()->create(['role_id' => tap(Role::factory()->create(), fn (Role $role) => $role->permissions()->attach(
            Permission::query()->whereIn('code', ['admin.access', 'orders.manage'])->pluck('id')
        ))->id]);

        $this->actingAs($orderStaff)->get(route('admin.payments.index'))->assertForbidden();
        $this->actingAs($orderStaff)->patch(route('admin.payments.review', $order), ['decision' => 'approve'])->assertForbidden();

        $this->assertSame('pending_review', $order->fresh()->payment_status);
    }

    public function test_reviewer_sees_the_queue_and_the_receipt_image(): void
    {
        $order = $this->transferOrder();
        $this->sendProof($order);
        $reviewer = $this->reviewer();

        $this->actingAs($reviewer)->get(route('admin.payments.index'))->assertOk()->assertSee($order->order_number);
        $this->actingAs($reviewer)->get(route('admin.payments.show', $order))->assertOk()->assertSee('FT26283123456');
        $this->actingAs($reviewer)->get(route('admin.payments.receipt', $order))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private');

        $this->actingAs($order->user)->get(route('admin.payments.receipt', $order))->assertForbidden();
    }

    public function test_approving_marks_the_order_paid(): void
    {
        $order = $this->transferOrder();
        $this->sendProof($order);
        $reviewer = $this->reviewer();

        $this->actingAs($reviewer)->patch(route('admin.payments.review', $order), ['decision' => 'approve'])
            ->assertRedirect(route('admin.payments.index'));

        $order->refresh();
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame($reviewer->id, $order->payment_reviewed_by);
        $this->assertNotNull($order->payment_reviewed_at);
        $this->assertSame(1, AuditLog::query()->where('action', 'payment.proof_approved')->count());
    }

    public function test_rejecting_needs_a_reason_and_lets_the_customer_resend(): void
    {
        $order = $this->transferOrder();
        $this->sendProof($order);
        $firstPath = $order->fresh()->payment_proof_path;
        $reviewer = $this->reviewer();

        $this->actingAs($reviewer)->patch(route('admin.payments.review', $order), ['decision' => 'reject'])
            ->assertSessionHasErrors('reason');

        $this->actingAs($reviewer)->patch(route('admin.payments.review', $order), [
            'decision' => 'reject',
            'reason' => 'Chưa thấy giao dịch trong sao kê',
        ]);

        $order->refresh();
        $this->assertSame('rejected', $order->payment_status);
        $this->assertSame('Chưa thấy giao dịch trong sao kê', $order->payment_rejection_reason);

        $this->actingAs($order->user)->get(route('orders.show', $order))
            ->assertSee('Chưa thấy giao dịch trong sao kê')
            ->assertSee('Gửi chứng từ');

        $this->sendProof($order)->assertSessionHas('status');
        $order->refresh();
        $this->assertSame('pending_review', $order->payment_status);
        $this->assertNull($order->payment_rejection_reason);
        Storage::disk('local')->assertMissing($firstPath);
        Storage::disk('local')->assertExists($order->payment_proof_path);
    }

    public function test_an_order_the_webhook_already_matched_is_not_reviewed_again(): void
    {
        $order = $this->transferOrder(['payment_status' => 'paid', 'transaction_code' => 'SEPAY-1']);

        $this->actingAs($this->reviewer())->patch(route('admin.payments.review', $order), ['decision' => 'reject', 'reason' => 'x'])
            ->assertSessionHas('error');

        $this->assertSame('paid', $order->fresh()->payment_status);
    }

    public function test_sidebar_counts_receipts_waiting_for_review(): void
    {
        $this->transferOrder(['payment_status' => 'pending_review']);
        $this->transferOrder(['payment_status' => 'pending_review']);
        $this->transferOrder();

        $this->actingAs($this->reviewer())->get(route('admin.payments.index'))
            ->assertSee('title="Chứng từ chờ duyệt">2</span>', false);
    }
}
