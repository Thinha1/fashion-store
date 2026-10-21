@extends('layouts.admin')

@section('title', 'Nhật ký hệ thống')

@section('content')
    @include('admin.partials.page-header', [
        'title' => 'Nhật ký hệ thống',
        'subtitle' => 'Ai đã làm gì, lúc nào, trên dữ liệu nào. Chỉ xem, không sửa hay xóa được.',
    ])

    @php($active = array_filter($filters, fn ($value) => $value !== null))

    <form method="GET" action="{{ route('admin.audit-logs.index') }}" class="mb-4 flex flex-wrap items-center gap-2">
        <label for="audit-action" class="sr-only">Hành động</label>
        <select id="audit-action" name="action" class="field min-h-10 sm:w-auto">
            <option value="">Mọi hành động</option>
            @foreach ($actions as $action)
                <option value="{{ $action }}" @selected($filters['action'] === $action)>{{ (new \App\Models\AuditLog(['action' => $action]))->actionLabel() }}</option>
            @endforeach
        </select>
        <label for="audit-actor" class="sr-only">Người thực hiện</label>
        <select id="audit-actor" name="actor" class="field min-h-10 sm:w-auto">
            <option value="">Mọi người thực hiện</option>
            <option value="system" @selected($filters['actor'] === 'system')>Hệ thống</option>
            @foreach ($actors as $actor)
                <option value="{{ $actor->id }}" @selected($filters['actor'] === $actor->id)>{{ $actor->name }}</option>
            @endforeach
        </select>
        <label for="audit-subject" class="sr-only">Loại dữ liệu</label>
        <select id="audit-subject" name="subject_type" class="field min-h-10 sm:w-auto">
            <option value="">Mọi loại dữ liệu</option>
            @foreach ($subjectTypes as $type)
                <option value="{{ $type }}" @selected($filters['subject_type'] === $type)>{{ \App\Models\AuditLog::SUBJECT_LABELS[class_basename($type)] ?? class_basename($type) }}</option>
            @endforeach
        </select>
        <label for="audit-from" class="text-sm text-gray-500">Từ</label>
        <input id="audit-from" type="date" name="from" value="{{ $filters['from'] }}" class="field min-h-10 sm:w-auto">
        <label for="audit-to" class="text-sm text-gray-500">đến</label>
        <input id="audit-to" type="date" name="to" value="{{ $filters['to'] }}" class="field min-h-10 sm:w-auto">
        <x-admin.action type="submit" variant="secondary" icon="search">Lọc</x-admin.action>
        @if ($active)
            <a href="{{ route('admin.audit-logs.index') }}" class="px-2 text-sm font-medium text-gray-500 hover:text-gray-900 hover:underline">Xoá lọc</a>
        @endif
    </form>

    <x-admin-table :paginator="$logs" :header="['Thời gian', 'Người thực hiện', 'Hành động', 'Dữ liệu', 'IP', 'Thao tác']">
        @forelse ($logs as $log)
            <tr>
                <td class="px-4 py-3 whitespace-nowrap text-gray-600">{{ $log->created_at?->format('d/m/Y H:i:s') }}</td>
                <td class="px-4 py-3 text-gray-900">
                    {{ $log->actor?->name ?? 'Hệ thống' }}
                    @if ($log->actor)
                        <span class="block text-xs text-gray-400">{{ $log->actor->email }}</span>
                    @endif
                </td>
                <td class="px-4 py-3 font-medium text-gray-900">{{ $log->actionLabel() }}</td>
                <td class="px-4 py-3 text-gray-600">{{ $log->subjectLabel() }}@if ($log->subject_id) #{{ $log->subject_id }}@endif</td>
                <td class="px-4 py-3 text-xs text-gray-500">{{ $log->ip_address ?? '—' }}</td>
                <td class="px-4 py-3 text-right"><div class="admin-row-actions">
                    <a href="{{ route('admin.audit-logs.show', $log) }}" class="admin-row-action"><x-icon name="eye" class="size-3.5" /> Xem</a>
                </div></td>
            </tr>
        @empty
            <tr>
                <td colspan="6" class="px-4 py-8 text-center text-gray-500">
                    {{ $active ? 'Không có nhật ký nào khớp bộ lọc.' : 'Chưa có nhật ký nào.' }}
                </td>
            </tr>
        @endforelse
    </x-admin-table>
@endsection
