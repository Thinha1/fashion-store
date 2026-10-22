@extends('layouts.admin')

@section('title', 'Cấu hình thanh toán QR')

@section('content')
    @include('admin.partials.page-header', [
        'title' => 'Cấu hình thanh toán QR',
        'subtitle' => 'Tài khoản nhận tiền cho mã VietQR và khóa webhook SePay. Để trống một mục nghĩa là dùng giá trị trong .env.',
    ])

    <x-admin.form-errors :messages="$errors->all()" />

    @unless ($effective->isEnabled())
        <p class="mb-6 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800" role="status">
            Chưa có mã ngân hàng và số tài khoản, nên trang thanh toán đang <strong>ẩn</strong> lựa chọn chuyển khoản.
        </p>
    @endunless

    @php
        $initial = [
            'bankId' => old('bank_id', $setting?->bank_id ?? ''),
            'accountNumber' => old('account_number', $setting?->account_number ?? ''),
            'accountName' => old('account_name', $setting?->account_name ?? ''),
            'fallback' => ['bankId' => $env->bankId(), 'accountNumber' => $env->accountNumber(), 'accountName' => $env->accountName()],
        ];
        $maskedApiKey = $setting?->maskedApiKey();
    @endphp

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_18rem]" x-data="paymentSettingsForm({{ Js::from($initial) }}, {{ Js::from(route('admin.settings.payment.test')) }})">
        <form method="POST" action="{{ route('admin.settings.payment.update') }}" class="admin-form admin-form-simple space-y-5">
            @csrf
            @method('PUT')

            <div class="admin-form-heading"><x-icon name="revenue" /><div>
                <h2>Tài khoản nhận tiền</h2>
                <p>Khách quét mã QR sẽ chuyển vào tài khoản này, với nội dung là mã đơn hàng.</p>
            </div></div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <x-label for="bank_id">Ngân hàng (mã VietQR hoặc BIN)</x-label>
                    <x-input id="bank_id" name="bank_id" list="vietqr-banks" x-model="bankId" maxlength="20" autocomplete="off"
                             placeholder="{{ $env->bankId() ?: 'VD: VCB, MB, 970436' }}" class="mt-1" />
                    <datalist id="vietqr-banks">
                        @foreach (\App\Models\PaymentSetting::BANKS as $code => $bank)
                            <option value="{{ $code }}">{{ $bank }}</option>
                        @endforeach
                    </datalist>
                    <x-input-error :messages="$errors->get('bank_id')" />
                </div>
                <div>
                    <x-label for="account_number">Số tài khoản</x-label>
                    <x-input id="account_number" name="account_number" inputmode="numeric" x-model="accountNumber" maxlength="30"
                             placeholder="{{ $env->accountNumber() ?: 'Chưa cấu hình' }}" class="mt-1" />
                    <x-input-error :messages="$errors->get('account_number')" />
                </div>
            </div>

            <div>
                <x-label for="account_name">Tên chủ tài khoản</x-label>
                <x-input id="account_name" name="account_name" x-model="accountName" maxlength="100"
                         placeholder="{{ $env->accountName() ?: 'VD: NGUYEN VAN A' }}" class="mt-1 uppercase" />
                <p class="mt-1 text-xs text-gray-500">Ghi đúng như ngân hàng in, không dấu. Hệ thống tự viết hoa.</p>
                <x-input-error :messages="$errors->get('account_name')" />
            </div>

            <div class="admin-form-heading"><x-icon name="key" /><div>
                <h2>Webhook SePay</h2>
                <p>Tại my.sepay.vn, vào Webhooks và thêm webhook cho sự kiện "Có tiền vào", kiểu chứng thực "API Key", với URL và key dưới đây.</p>
            </div></div>

            <div>
                <x-label for="webhook_url">URL webhook</x-label>
                <x-input id="webhook_url" type="url" name="webhook_url" value="{{ old('webhook_url', $setting?->webhook_url) }}" maxlength="255"
                         placeholder="{{ route('webhooks.sepay') }}" class="mt-1 font-mono text-xs" />
                <p class="mt-1 text-xs text-gray-500">Địa chỉ công khai SePay gọi tới, dán y hệt vào SePay. Chạy trên máy cá nhân thì dùng Tailscale Funnel, VD: https://ten-may.ts.net/webhooks/sepay. Để trống thì dùng {{ route('webhooks.sepay') }}.</p>
                <x-input-error :messages="$errors->get('webhook_url')" />
            </div>

            <div>
                <x-label for="sepay_webhook_api_key">API key</x-label>
                @if ($maskedApiKey)
                    <p class="mt-1 text-sm text-gray-500">Hiện tại: <code class="rounded bg-gray-100 px-1.5 py-0.5">{{ $maskedApiKey }}</code></p>
                @elseif ($env->webhookApiKey() !== '')
                    <p class="mt-1 text-sm text-gray-500">Đang dùng key trong .env.</p>
                @endif
                <div class="mt-1 flex gap-2">
                    <x-input id="sepay_webhook_api_key" type="text" name="sepay_webhook_api_key" x-model="apiKey" autocomplete="off" spellcheck="false"
                             placeholder="{{ $maskedApiKey ? 'Để trống để giữ nguyên key hiện tại' : 'Chưa cấu hình' }}" class="font-mono text-xs" />
                    <button type="button" class="admin-action admin-action-secondary shrink-0" x-on:click="generateKey()">Tạo key</button>
                </div>
                <p class="mt-1 text-xs text-gray-500" x-show="apiKey" x-cloak>Chép key này sang SePay trước khi lưu. Sau khi lưu chỉ còn hiện 4 ký tự cuối.</p>
                <x-input-error :messages="$errors->get('sepay_webhook_api_key')" />
                @if ($maskedApiKey)
                    <label class="mt-2 flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" name="clear_sepay_webhook_api_key" value="1" class="rounded border-gray-300 text-gray-900 focus:ring-gray-500">
                        Xóa key đã lưu (chuyển về dùng giá trị trong .env)
                    </label>
                @endif
            </div>

            <div class="rounded-lg border border-gray-200 p-4">
                <button type="button" x-on:click="test()" x-bind:disabled="testing" class="admin-action admin-action-secondary">
                    <x-icon name="send" class="size-4" />
                    <span x-text="testing ? 'Đang kiểm tra…' : 'Kiểm tra webhook'">Kiểm tra webhook</span>
                </button>
                <p class="mt-2 text-xs text-gray-500">Gọi thử URL webhook và API key <strong>đã lưu</strong> giống như SePay sẽ gọi, bằng một giao dịch tiền ra mà hệ thống bỏ qua, nên không ảnh hưởng đơn hàng nào.</p>
                <p x-cloak x-show="testResult" x-text="testResult" role="status"
                   :class="testOk ? 'mt-2 text-sm text-brand' : 'mt-2 text-sm text-red-700'"></p>
            </div>

            @if ($setting?->updated_at)
                <p class="text-xs text-gray-500">Cập nhật lần cuối: {{ $setting->updated_at->format('d/m/Y H:i') }}@if($setting->updatedBy) bởi {{ $setting->updatedBy->name }}@endif</p>
            @endif

            <x-admin.form-actions :cancel="route('admin.settings.payment.edit')" label="Lưu cấu hình" />
        </form>

        <x-admin.panel title="Xem trước mã QR" icon="eye" class="self-start">
            <template x-if="qrUrl()">
                <img :src="qrUrl()" alt="Mã QR xem trước" class="mx-auto w-full max-w-60 rounded-lg border border-gray-200" loading="lazy">
            </template>
            <p x-show="!qrUrl()" class="text-sm text-gray-500">Nhập ngân hàng và số tài khoản để xem mã QR.</p>
            <p class="mt-3 text-xs text-gray-500">Mã mẫu 10.000đ, nội dung "DH TEST". Quét thử bằng app ngân hàng để chắc tên và số tài khoản đúng, không cần chuyển tiền.</p>
        </x-admin.panel>
    </div>
@endsection
