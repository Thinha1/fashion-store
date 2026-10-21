@extends('layouts.admin')

@section('title', 'Nhật ký #'.$log->id)

@section('content')
    @include('admin.partials.page-header', [
        'title' => $log->actionLabel(),
        'subtitle' => 'Nhật ký #'.$log->id.' · '.$log->created_at?->format('H:i:s d/m/Y'),
    ])

    @php
        $subjectUrl = $log->subjectUrl();
        $format = fn ($value) => match (true) {
            $value === null => '—',
            is_bool($value) => $value ? 'Có' : 'Không',
            is_array($value) => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            default => (string) $value,
        };
        $compare = $old !== [] && $new !== [];
    @endphp

    <div class="space-y-6">
        <x-admin.panel title="Thông tin" icon="info">
            <dl class="grid gap-x-8 gap-y-3 text-sm sm:grid-cols-2">
                <div><dt class="text-gray-500">Người thực hiện</dt><dd class="text-gray-900">{{ $log->actor ? $log->actor->name.' ('.$log->actor->email.')' : 'Hệ thống' }}</dd></div>
                <div><dt class="text-gray-500">Hành động</dt><dd class="text-gray-900">{{ $log->actionLabel() }} <span class="text-xs text-gray-400">({{ $log->action }})</span></dd></div>
                <div><dt class="text-gray-500">Dữ liệu</dt><dd class="text-gray-900">
                    {{ $log->subjectLabel() }}@if ($log->subject_id) #{{ $log->subject_id }}@endif
                    @if ($subjectUrl)
                        · <a href="{{ $subjectUrl }}" class="font-medium text-brand hover:underline">Mở bản ghi</a>
                    @elseif ($log->subject_id)
                        <span class="text-xs text-gray-400">(không còn tồn tại)</span>
                    @endif
                </dd></div>
                <div><dt class="text-gray-500">Thời gian</dt><dd class="text-gray-900">{{ $log->created_at?->format('H:i:s d/m/Y') }}</dd></div>
                <div><dt class="text-gray-500">Địa chỉ IP</dt><dd class="text-gray-900">{{ $log->ip_address ?? '—' }}</dd></div>
                <div><dt class="text-gray-500">Trình duyệt</dt><dd class="break-all text-gray-900">{{ $log->user_agent ?? '—' }}</dd></div>
            </dl>
        </x-admin.panel>

        <x-admin.panel title="Dữ liệu thay đổi" icon="edit">
            @if ($fields === [])
                <p class="text-sm text-gray-500">Không có dữ liệu kèm theo.</p>
            @else
                <x-admin-table :header="['Trường', 'Trước', 'Sau']">
                    @foreach ($fields as $field)
                        @php($before = $old[$field] ?? null)
                        @php($after = $new[$field] ?? null)
                        <tr @class(['bg-amber-50/60' => $compare && $before !== $after])>
                            <td class="px-4 py-3 align-top font-medium text-gray-900">{{ $field }}</td>
                            <td class="px-4 py-3 align-top text-gray-600"><pre class="font-sans text-sm whitespace-pre-wrap">{{ $format($before) }}</pre></td>
                            <td class="px-4 py-3 align-top text-gray-900"><pre class="font-sans text-sm whitespace-pre-wrap">{{ $format($after) }}</pre></td>
                        </tr>
                    @endforeach
                </x-admin-table>
            @endif
        </x-admin.panel>
    </div>
@endsection
