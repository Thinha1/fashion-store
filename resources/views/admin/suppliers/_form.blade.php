@php($route = $route ?? route('admin.suppliers.store'))
@php($method = $method ?? 'POST')

<form method="POST" action="{{ $route }}" class="admin-form max-w-2xl space-y-4">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <div>
        <x-label for="name">Tên nhà cung cấp</x-label>
        <x-input id="name" name="name" value="{{ old('name', $supplier->name) }}" required class="mt-1" />
        <x-input-error :messages="$errors->get('name')" />
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <x-label for="phone">Điện thoại</x-label>
            <x-input id="phone" name="phone" value="{{ old('phone', $supplier->phone) }}" required class="mt-1" />
            <x-input-error :messages="$errors->get('phone')" />
        </div>
        <div>
            <x-label for="email">Email</x-label>
            <x-input id="email" type="email" name="email" value="{{ old('email', $supplier->email) }}" class="mt-1" />
            <x-input-error :messages="$errors->get('email')" />
        </div>
    </div>

    <div>
        <x-label for="address">Địa chỉ</x-label>
        <textarea id="address" name="address" rows="2" required
                  class="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-gray-500 focus:outline-none focus:ring-1 focus:ring-gray-500">{{ old('address', $supplier->address) }}</textarea>
        <x-input-error :messages="$errors->get('address')" />
    </div>

    <div>
        <x-label for="tax_code">Mã số thuế (tùy chọn)</x-label>
        <x-input id="tax_code" name="tax_code" value="{{ old('tax_code', $supplier->tax_code) }}" class="mt-1" />
        <x-input-error :messages="$errors->get('tax_code')" />
    </div>

    <div>
        <label class="flex items-center gap-2 text-sm text-gray-700">
            <input type="checkbox" name="is_active" value="1"
                   @checked(old('is_active', $supplier->is_active ?? true))
                   class="rounded border-gray-300 text-gray-900 focus:ring-gray-500">
            Đang hoạt động
        </label>
    </div>

    <div class="flex items-center gap-2">
        <x-button type="submit">Lưu</x-button>
        <a href="{{ route('admin.suppliers.index') }}" class="text-sm text-gray-600 hover:underline">Hủy</a>
    </div>
</form>
