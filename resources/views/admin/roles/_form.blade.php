@php
    $checked = old('permissions', $granted);
    $mine = auth()->user();
@endphp

<x-admin.form-errors :messages="$errors->all()" />

<form method="POST" action="{{ $role->exists ? route('admin.roles.update', $role) : route('admin.roles.store') }}" class="admin-form space-y-5">
    @csrf
    @if ($role->exists)
        @method('PUT')
    @endif

    <div class="admin-form-heading"><x-icon name="key" /><div>
        <h2>Thông tin vai trò</h2>
        <p>@if ($role->exists) Mã vai trò: <code>{{ $role->code }}</code> (không đổi được). @else Mã vai trò được tạo từ tên. @endif</p>
    </div></div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <x-label for="name">Tên vai trò</x-label>
            <x-input id="name" name="name" value="{{ old('name', $role->name) }}" required maxlength="100" placeholder="VD: Nhân viên bán hàng" class="mt-1" />
            <x-input-error :messages="$errors->get('name')" />
            <x-input-error :messages="$errors->get('code')" />
        </div>
        <div>
            <x-label for="description">Mô tả</x-label>
            <x-input id="description" name="description" value="{{ old('description', $role->description) }}" maxlength="500" class="mt-1" />
            <x-input-error :messages="$errors->get('description')" />
        </div>
    </div>

    <div class="admin-form-heading"><x-icon name="check" /><div>
        <h2>Quyền</h2>
        <p>
            @if ($locked)
                Vai trò hệ thống: quyền cố định ({{ $role->code === 'admin' ? 'luôn có toàn bộ quyền' : 'không có quyền quản trị' }}).
            @else
                Chỉ chọn được quyền mà chính bạn đang có.
            @endif
        </p>
    </div></div>

    <div class="grid gap-4 md:grid-cols-2" x-data>
        @foreach ($groups as $group => $permissions)
            <fieldset class="rounded-xl border border-gray-200 p-4">
                <legend class="px-1 text-sm font-semibold text-gray-900">{{ $group }}</legend>
                <div class="mt-1 space-y-2">
                    @foreach ($permissions as $permission)
                        @php($disabled = $locked || ! $mine->hasPermission($permission->code))
                        <label @class(['flex items-start gap-2 text-sm', 'text-gray-400' => $disabled, 'text-gray-800' => ! $disabled])>
                            <input type="checkbox" name="permissions[]" value="{{ $permission->code }}"
                                   @checked(in_array($permission->code, $checked, true)) @disabled($disabled)
                                   class="mt-0.5 rounded border-gray-300 text-brand focus:ring-brand">
                            <span>
                                {{ $permission->name }}
                                <span class="block text-xs text-gray-400">{{ $permission->code }}@if (in_array($permission->code, \App\Support\PermissionGroups::NOT_BUILT, true)) · chưa có màn hình @endif</span>
                            </span>
                        </label>
                        {{-- A permission you cannot grant but the role already has stays on it. --}}
                        @if ($disabled && ! $locked && in_array($permission->code, $checked, true))
                            <input type="hidden" name="permissions[]" value="{{ $permission->code }}">
                        @endif
                    @endforeach
                </div>
            </fieldset>
        @endforeach
    </div>

    @if ($role->updated_at)
        <p class="text-xs text-gray-500">Cập nhật lần cuối: {{ $role->updated_at->format('d/m/Y H:i') }}@if ($role->updatedBy) bởi {{ $role->updatedBy->name }}@endif</p>
    @endif

    <x-admin.form-actions :cancel="route('admin.roles.index')" :label="$role->exists ? 'Lưu vai trò' : 'Tạo vai trò'" />
</form>
