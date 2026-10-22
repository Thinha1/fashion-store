<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SavePaymentSettingRequest;
use App\Models\PaymentSetting;
use App\Services\Audit\AuditLogger;
use App\Services\Payments\BankTransfer;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;

/**
 * Bank-transfer settings: the receiving account the VietQR code points to and
 * the SePay webhook (URL + key) — editable here instead of in .env. An empty
 * field keeps using the .env value (see BankTransfer).
 */
class PaymentSettingController extends Controller
{
    public function edit(BankTransfer $bankTransfer): View
    {
        return view('admin.settings.payment', [
            'setting' => PaymentSetting::current(),
            'effective' => $bankTransfer,
            'env' => new BankTransfer,
        ]);
    }

    public function update(SavePaymentSettingRequest $request): RedirectResponse
    {
        $setting = PaymentSetting::current() ?? new PaymentSetting;
        $old = $this->auditValues($setting);

        $setting->fill([
            'bank_id' => strtoupper($request->string('bank_id')->trim()->value()) ?: null,
            'account_number' => $request->string('account_number')->trim()->value() ?: null,
            // VietQR shows the holder's name as the bank has it: upper case.
            'account_name' => mb_strtoupper($request->string('account_name')->squish()->value()) ?: null,
            'webhook_url' => $request->string('webhook_url')->trim()->value() ?: null,
            'updated_by' => Auth::id(),
        ]);

        $keyChanged = false;
        if ($request->boolean('clear_sepay_webhook_api_key')) {
            $keyChanged = $setting->sepay_webhook_api_key !== null;
            $setting->sepay_webhook_api_key = null;
        } elseif ($request->filled('sepay_webhook_api_key')) {
            $keyChanged = true;
            $setting->sepay_webhook_api_key = $request->string('sepay_webhook_api_key')->trim()->value();
        }

        $setting->save();

        // Never the key itself — only whether this save touched it.
        app(AuditLogger::class)->record(
            'payment_settings.updated',
            $setting,
            $old,
            $this->auditValues($setting) + ['api_key_changed' => $keyChanged],
        );

        return redirect()->route('admin.settings.payment.edit')->with('status', 'Đã lưu cấu hình thanh toán.');
    }

    /**
     * Calls the saved webhook URL from the outside, the way SePay will, with
     * an outgoing-transfer payload the webhook ignores — so it proves the URL
     * is reachable and the key accepted without touching any order.
     */
    public function test(BankTransfer $bankTransfer): JsonResponse
    {
        if ($bankTransfer->webhookApiKey() === '') {
            return response()->json(['ok' => false, 'message' => 'Chưa có API key. Nhập key và bấm Lưu trước khi kiểm tra.']);
        }

        try {
            $response = Http::timeout(15)->acceptJson()
                ->withHeaders(['Authorization' => 'Apikey '.$bankTransfer->webhookApiKey()])
                ->post($bankTransfer->webhookUrl(), ['id' => 0, 'transferType' => 'out', 'transferAmount' => 0, 'content' => 'Kiểm tra webhook']);
        } catch (ConnectionException) {
            return response()->json(['ok' => false, 'message' => 'Không gọi được '.$bankTransfer->webhookUrl().'. URL có mở ra internet chưa (tên miền, Tailscale Funnel)?']);
        }

        $message = match (true) {
            $response->successful() && $response->json('success') === true => null,
            $response->status() === 401 => 'URL gọi được nhưng API key bị từ chối. Bấm Lưu rồi thử lại.',
            default => 'URL trả về HTTP '.$response->status().', không phải webhook SePay của cửa hàng này.',
        };

        return response()->json($message === null
            ? ['ok' => true, 'message' => 'Webhook hoạt động: SePay sẽ gọi được '.$bankTransfer->webhookUrl().'.']
            : ['ok' => false, 'message' => $message]);
    }

    /**
     * @return array<string, ?string>
     */
    private function auditValues(PaymentSetting $setting): array
    {
        return [
            'bank_id' => $setting->bank_id,
            'account_number' => $setting->account_number,
            'account_name' => $setting->account_name,
            'webhook_url' => $setting->webhook_url,
        ];
    }
}
