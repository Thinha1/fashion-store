@extends('layouts.admin')

@section('title', 'Cấu hình AI')

@section('content')
    @include('admin.partials.page-header', ['title' => 'Cấu hình AI'])

    <x-admin.form-errors :messages="$errors->all()" />

    @php
        $initial = [
            'endpoint' => old('endpoint', $setting?->endpoint ?? ''),
            'model' => old('model', $setting?->model ?? ''),
            'maxTokens' => old('max_tokens', $setting?->max_tokens ?? ''),
        ];
    @endphp
    <form method="POST" action="{{ route('admin.settings.ai.update') }}" class="admin-form admin-form-simple space-y-5"
          x-data="aiSettingsForm({{ Js::from($initial) }}, {{ Js::from(route('admin.settings.ai.test')) }})">
        @csrf
        @method('PUT')

        <div class="admin-form-heading"><x-icon name="settings" /><div><h2>Backend AI dùng chung</h2><p>Áp dụng cho cả trợ lý soạn sản phẩm (admin) lẫn trợ lý gợi ý sản phẩm (khách hàng) — để trống một mục nghĩa là dùng giá trị mặc định từ cấu hình máy chủ (.env).</p></div></div>

        <div>
            <x-label for="endpoint">Endpoint (URL đầy đủ, thường kết thúc bằng "/v1")</x-label>
            <x-input id="endpoint" name="endpoint" x-model="endpoint" placeholder="https://your-ai-host.example.com/v1" class="mt-1" />
            <x-input-error :messages="$errors->get('endpoint')" />
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <x-label for="model">Model</x-label>
                <x-input id="model" name="model" x-model="model" class="mt-1" />
                <x-input-error :messages="$errors->get('model')" />
            </div>
            <div>
                <x-label for="max_tokens">Max tokens</x-label>
                <x-input id="max_tokens" type="number" min="1" name="max_tokens" x-model="maxTokens" class="mt-1" />
                <x-input-error :messages="$errors->get('max_tokens')" />
            </div>
        </div>

        <div>
            <x-label for="api_key">API key</x-label>
            @if ($maskedApiKey)
                <p class="mt-1 text-sm text-gray-500">Hiện tại: <code class="rounded bg-gray-100 px-1.5 py-0.5">{{ $maskedApiKey }}</code></p>
            @endif
            <x-input id="api_key" type="password" name="api_key" x-model="apiKey" autocomplete="off"
                     placeholder="{{ $maskedApiKey ? 'Để trống để giữ nguyên key hiện tại' : 'Chưa cấu hình' }}" class="mt-1" />
            <x-input-error :messages="$errors->get('api_key')" />
            @if ($maskedApiKey)
                <label class="mt-2 flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" name="clear_api_key" value="1" x-model="clearApiKey"
                           class="rounded border-gray-300 text-gray-900 focus:ring-gray-500">
                    Xoá API key (chuyển về dùng giá trị mặc định từ .env)
                </label>
            @endif
        </div>

        <div class="rounded-lg border border-gray-200 p-4">
            <button type="button" x-on:click="test()" x-bind:disabled="testing" class="admin-action admin-action-secondary">
                <x-icon name="chat" class="size-4" />
                <span x-text="testing ? 'Đang kiểm tra…' : 'Kiểm tra kết nối'">Kiểm tra kết nối</span>
            </button>
            <p class="mt-2 text-xs text-gray-500">Thử gọi ngay backend với thông tin đang nhập ở trên (chưa cần lưu) để phát hiện sai endpoint/model/key trước khi áp dụng thật.</p>
            <p x-cloak x-show="testResult" x-text="testResult" role="status"
               :class="testOk ? 'mt-2 text-sm text-brand' : 'mt-2 text-sm text-red-700'"></p>
        </div>

        @if ($setting?->updated_at)
            <p class="text-xs text-gray-500">Cập nhật lần cuối: {{ $setting->updated_at->format('d/m/Y H:i') }}@if($setting->updatedBy) bởi {{ $setting->updatedBy->name }}@endif</p>
        @endif

        <x-admin.form-actions :cancel="route('admin.dashboard')" label="Lưu cấu hình" />
    </form>
@endsection
