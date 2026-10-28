@extends('layouts.admin')

@section('title', 'Vai trò & quyền')

@section('content')
    @include('admin.partials.page-header', [
        'title' => 'Vai trò & quyền',
        'subtitle' => 'Mỗi tài khoản có một vai trò; vai trò quyết định những trang và thao tác được dùng.',
        'actionUrl' => route('admin.roles.create'),
        'actionLabel' => 'Thêm vai trò',
    ])

    <x-admin-table :header="['Vai trò', 'Mô tả', 'Quyền', 'Tài khoản', 'Thao tác']">
        @foreach ($roles as $role)
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900">
                    {{ $role->name }}
                    @if ($access->isLocked($role))
                        <span class="ml-1 rounded bg-gray-100 px-1.5 py-0.5 text-xs font-normal text-gray-500">hệ thống</span>
                    @endif
                    <span class="block text-xs font-normal text-gray-400">{{ $role->code }}</span>
                </td>
                <td class="px-4 py-3 text-gray-600">{{ $role->description ?: '—' }}</td>
                <td class="px-4 py-3 text-gray-600">{{ $role->permissions_count }}/{{ $permissionTotal }}</td>
                <td class="px-4 py-3 text-gray-600">
                    @if ($role->code !== 'customer' && $role->users_count > 0)
                        <a href="{{ route('admin.staff.index', ['role' => $role->id]) }}" class="text-brand hover:underline">{{ $role->users_count }}</a>
                    @else
                        {{ $role->users_count }}
                    @endif
                </td>
                <td class="px-4 py-3 text-right"><div class="admin-row-actions">
                    <a href="{{ route('admin.roles.edit', $role) }}" class="admin-row-action"><x-icon name="edit" class="size-3.5" /> {{ $access->isLocked($role) ? 'Xem' : 'Sửa' }}</a>
                    @unless ($access->isLocked($role))
                        <form method="POST" action="{{ route('admin.roles.destroy', $role) }}" class="inline"
                              x-on:submit.prevent="$dispatch('admin-confirm', { form: $el, message: {{ Js::from('Xóa vai trò "'.$role->name.'"?') }} })">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="admin-row-action"><x-icon name="delete" class="size-3.5" /> Xóa</button>
                        </form>
                    @endunless
                </div></td>
            </tr>
        @endforeach
    </x-admin-table>
@endsection
