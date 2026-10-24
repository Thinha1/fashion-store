@php($isSelf = $staff->exists && $staff->is(auth()->user()))

<x-admin.form-errors :messages="$errors->all()" />

<form method="POST" action="{{ $staff->exists ? route('admin.staff.update', $staff) : route('admin.staff.store') }}" class="admin-form admin-form-simple space-y-5">
    @csrf
    @if ($staff->exists)
        @method('PUT')
    @endif

    <div class="admin-form-heading"><x-icon name="user" /><div>
        <h2>Thông tin tài khoản</h2>
        <p>Nhân viên đăng nhập bằng email và mật khẩu này tại trang đăng nhập của cửa hàng.</p>
    </div></div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <x-label for="name">Họ tên</x-label>
            <x-input id="name" name="name" value="{{ old('name', $staff->name) }}" required maxlength="255" class="mt-1" />
            <x-input-error :messages="$errors->get('name')" />
        </div>
        <div>
            <x-label for="phone">Số điện thoại</x-label>
            <x-input id="phone" name="phone" value="{{ old('phone', $staff->phone) }}" maxlength="20" inputmode="tel" class="mt-1" />
            <x-input-error :messages="$errors->get('phone')" />
        </div>
    </div>

    <div>
        <x-label for="email">Email</x-label>
        <x-input id="email" type="email" name="email" value="{{ old('email', $staff->email) }}" required maxlength="255" autocomplete="off" class="mt-1" />
        <x-input-error :messages="$errors->get('email')" />
    </div>

    <div>
        <x-label for="password">{{ $staff->exists ? 'Mật khẩu mới' : 'Mật khẩu' }}</x-label>
        <x-input id="password" type="password" name="password" autocomplete="new-password" minlength="8" :required="! $staff->exists"
                 placeholder="{{ $staff->exists ? 'Để trống để giữ mật khẩu hiện tại' : 'Ít nhất 8 ký tự' }}" class="mt-1" />
        <x-input-error :messages="$errors->get('password')" />
    </div>

    <div class="admin-form-heading"><x-icon name="key" /><div>
        <h2>Vai trò và trạng thái</h2>
        <p>Vai trò quyết định nhân viên được dùng những trang nào. Tài khoản bị khóa không đăng nhập được nhưng vẫn giữ lịch sử thao tác.</p>
    </div></div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <x-label for="role_id">Vai trò</x-label>
            <select id="role_id" name="role_id" required @disabled($isSelf) class="field mt-1">
                <option value="">— Chọn vai trò —</option>
                @foreach ($roles as $role)
                    <option value="{{ $role->id }}" @selected((int) old('role_id', $staff->role_id) === $role->id)>{{ $role->name }} ({{ $role->permissions->count() }} quyền)</option>
                @endforeach
            </select>
            @if ($isSelf)
                <input type="hidden" name="role_id" value="{{ $staff->role_id }}">
                <p class="mt-1 text-xs text-gray-500">Bạn không thể tự đổi vai trò của chính mình.</p>
            @endif
            <x-input-error :messages="$errors->get('role_id')" />
        </div>
        <div>
            <x-label for="status">Trạng thái</x-label>
            <select id="status" name="status" required @disabled($isSelf) class="field mt-1">
                <option value="active" @selected(old('status', $staff->status === 'locked' ? 'locked' : 'active') === 'active')>Đang hoạt động</option>
                <option value="locked" @selected(old('status', $staff->status === 'locked' ? 'locked' : 'active') === 'locked')>Đã khóa</option>
            </select>
            @if ($isSelf)
                <input type="hidden" name="status" value="active">
            @endif
            <x-input-error :messages="$errors->get('status')" />
        </div>
    </div>

    <x-admin.form-actions :cancel="route('admin.staff.index')" :label="$staff->exists ? 'Lưu tài khoản' : 'Tạo tài khoản'" />
</form>
