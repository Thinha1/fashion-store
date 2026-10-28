@extends('layouts.admin')

@section('title', 'Nhân viên')

@section('content')
    @include('admin.partials.page-header', [
        'title' => 'Nhân viên',
        'actionUrl' => route('admin.staff.create'),
        'actionLabel' => 'Thêm nhân viên',
    ])

    @php($active = array_filter($filters, fn ($value) => $value !== null))

    <form method="GET" action="{{ route('admin.staff.index') }}" class="mb-4 flex flex-wrap items-center gap-2">
        <label for="staff-q" class="sr-only">Tìm nhân viên</label>
        <input id="staff-q" type="search" name="q" value="{{ $filters['q'] }}" placeholder="Tên, email hoặc số điện thoại" class="field min-h-10 sm:w-64">
        <label for="staff-role" class="sr-only">Vai trò</label>
        <select id="staff-role" name="role" class="field min-h-10 sm:w-auto">
            <option value="">Mọi vai trò</option>
            @foreach ($roles as $role)
                <option value="{{ $role->id }}" @selected($filters['role'] === $role->id)>{{ $role->name }}</option>
            @endforeach
        </select>
        <label for="staff-status" class="sr-only">Trạng thái</label>
        <select id="staff-status" name="status" class="field min-h-10 sm:w-auto">
            <option value="">Mọi trạng thái</option>
            <option value="active" @selected($filters['status'] === 'active')>Đang hoạt động</option>
            <option value="locked" @selected($filters['status'] === 'locked')>Đã khóa</option>
        </select>
        <x-admin.action type="submit" variant="secondary" icon="search">Lọc</x-admin.action>
        @if ($active)
            <a href="{{ route('admin.staff.index') }}" class="px-2 text-sm font-medium text-gray-500 hover:text-gray-900 hover:underline">Xoá lọc</a>
        @endif
    </form>

    <x-admin-table :paginator="$staff" :header="['Nhân viên', 'Số điện thoại', 'Vai trò', 'Trạng thái', 'Thao tác']">
        @forelse ($staff as $member)
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900">
                    {{ $member->name }}
                    @if ($member->is(auth()->user()))
                        <span class="ml-1 rounded bg-brand-soft px-1.5 py-0.5 text-xs font-normal text-brand">bạn</span>
                    @endif
                    <span class="block text-xs font-normal text-gray-400">{{ $member->email }}</span>
                </td>
                <td class="px-4 py-3 text-gray-600">{{ $member->phone ?: '—' }}</td>
                <td class="px-4 py-3 text-gray-600">{{ $member->role?->name }}</td>
                <td class="px-4 py-3"><x-admin.status :value="$member->isActive()" active-label="Đang hoạt động" inactive-label="Đã khóa" /></td>
                <td class="px-4 py-3 text-right"><div class="admin-row-actions">
                    <a href="{{ route('admin.staff.edit', $member) }}" class="admin-row-action"><x-icon name="edit" class="size-3.5" /> Sửa</a>
                </div></td>
            </tr>
        @empty
            <tr>
                <td colspan="5" class="px-4 py-8 text-center text-gray-500">{{ $active ? 'Không có nhân viên nào khớp bộ lọc.' : 'Chưa có tài khoản nhân viên nào.' }}</td>
            </tr>
        @endforelse
    </x-admin-table>
@endsection
