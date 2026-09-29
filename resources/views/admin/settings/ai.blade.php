@extends('layouts.admin')

@section('title', 'Cấu hình AI')

@section('content')
    @include('admin.partials.page-header', [
        'title' => 'Cấu hình AI'
    ])

    <x-admin.form-errors :messages="$errors->all()" />

    @if (! $settings->contains('is_primary', true))
        <p class="mb-6 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800" role="status">
            Chưa có cấu hình nào được chọn làm chính, hệ thống đang dùng giá trị mặc định từ .env
            @if ($envDefaults['endpoint'] !== '' || $envDefaults['model'] !== '')
                (endpoint: <code>{{ $envDefaults['endpoint'] ?: '—' }}</code>, model: <code>{{ $envDefaults['model'] ?: '—' }}</code>)
            @endif.
        </p>
    @endif

    @php
        $initial = [
            'settingId' => $editing?->id,
            'name' => old('name', $editing?->name ?? ''),
            'endpoint' => old('endpoint', $editing?->endpoint ?? ''),
            'model' => old('model', $editing?->model ?? ''),
            'maxTokens' => old('max_tokens', $editing?->max_tokens ?? ''),
        ];
        $maskedApiKey = $editing?->maskedApiKey();
    @endphp
    <form method="POST" action="{{ $editing ? route('admin.settings.ai.update', $editing) : route('admin.settings.ai.store') }}"
          class="admin-form admin-form-simple mb-10 space-y-5"
          x-data="aiSettingsForm({{ Js::from($initial) }}, {{ Js::from(route('admin.settings.ai.test')) }})">
        @csrf
        @if ($editing)
            @method('PUT')
        @endif

        <div class="admin-form-heading"><x-icon name="settings" /><div>
            <h2>{{ $editing ? 'Sửa cấu hình: '.($editing->name ?? 'Chưa đặt tên') : 'Thêm cấu hình mới' }}</h2>
            <p>Để trống một mục nghĩa là dùng giá trị mặc định từ cấu hình máy chủ (.env).</p>
        </div></div>

        <div>
            <x-label for="name">Tên cấu hình</x-label>
            <x-input id="name" name="name" x-model="name" placeholder="VD: DeepSeek, Qwen nội bộ" required maxlength="100" class="mt-1" />
            <x-input-error :messages="$errors->get('name')" />
        </div>

        <div>
            <x-label for="endpoint">Base URL (URL đầy đủ, thường kết thúc bằng "/v1")</x-label>
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

        @if ($editing?->updated_at)
            <p class="text-xs text-gray-500">Cập nhật lần cuối: {{ $editing->updated_at->format('d/m/Y H:i') }}@if($editing->updatedBy) bởi {{ $editing->updatedBy->name }}@endif</p>
        @endif

        <x-admin.form-actions :cancel="route('admin.settings.ai.edit')" :label="$editing ? 'Lưu cấu hình' : 'Thêm cấu hình'" />
    </form>

    {{-- KHỐI BẢNG DANH SÁCH ĐƯỢC CHUYỂN XUỐNG DƯỚI --}}
    <x-admin-table :header="['Tên', 'Base URL', 'Model', 'API key', 'Trạng thái', 'Thao tác']">
        @forelse ($settings as $setting)
            <tr @class(['bg-emerald-50/40' => $setting->is_primary])>
                <td class="px-4 py-3 font-medium text-gray-900">{{ $setting->name ?? 'Chưa đặt tên' }}</td>
                <td class="px-4 py-3 text-gray-600 break-all">{{ $setting->endpoint ?: '— (mặc định .env)' }}</td>
                <td class="px-4 py-3 text-gray-600">{{ $setting->model ?: '— (mặc định .env)' }}</td>
                <td class="px-4 py-3 text-gray-600">
                    @if ($masked = $setting->maskedApiKey())
                        <code class="rounded bg-gray-100 px-1.5 py-0.5">{{ $masked }}</code>
                    @else
                        — (mặc định .env)
                    @endif
                </td>
                <td class="px-4 py-3">
                    <x-admin.status :value="$setting->is_primary" active-label="Đang dùng" inactive-label="Không dùng" />
                </td>
                <td class="px-4 py-3 text-right"><div class="admin-row-actions">
                    @unless ($setting->is_primary)
                        <form method="POST" action="{{ route('admin.settings.ai.primary', $setting) }}" class="inline"
                              x-on:submit.prevent="$dispatch('admin-confirm', { form: $el, message: 'Chuyển sang dùng cấu hình &quot;{{ e($setting->name) }}&quot; cho tất cả trợ lý AI?' })">
                            @csrf
                            <button type="submit" class="admin-row-action"><x-icon name="check" class="size-3.5" /> Đặt làm chính</button>
                        </form>
                    @endunless
                    <a href="{{ route('admin.settings.ai.edit', ['sua' => $setting->id]) }}" class="admin-row-action"><x-icon name="edit" class="size-3.5" /> Sửa</a>
                    @unless ($setting->is_primary)
                        <form method="POST" action="{{ route('admin.settings.ai.destroy', $setting) }}" class="inline"
                              x-on:submit.prevent="$dispatch('admin-confirm', { form: $el, message: 'Xóa cấu hình này?' })">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="admin-row-action"><x-icon name="delete" class="size-3.5" /> Xóa</button>
                        </form>
                    @endunless
                </div></td>
            </tr>
        @empty
            <tr>
                <td colspan="6" class="px-4 py-8 text-center text-gray-500">Chưa có cấu hình nào. Thêm cấu hình đầu tiên bên trên, nó sẽ tự động được dùng làm cấu hình chính.</td>
            </tr>
        @endforelse
    </x-admin-table>
@endsection