@php($route = $route ?? 'admin.brands.store')
@php($method = $method ?? 'POST')

<form method="POST" action="{{ $route }}" class="max-w-2xl space-y-4">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <div>
        <x-label for="name">Tên thương hiệu</x-label>
        <x-input id="name" name="name" value="{{ old('name', $brand->name) }}" required class="mt-1" />
        <x-input-error :messages="$errors->get('name')" />
    </div>

    <div>
        <x-label for="slug">Slug</x-label>
        <x-input id="slug" name="slug" value="{{ old('slug', $brand->slug) }}" required class="mt-1" />
        <x-input-error :messages="$errors->get('slug')" />
        <p class="mt-1 text-xs text-gray-500">Dùng để định danh URL, không đổi sau khi lưu.</p>
    </div>

    <div>
        <x-label for="description">Mô tả</x-label>
        <textarea id="description" name="description" rows="3"
                  class="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-gray-500 focus:outline-none focus:ring-1 focus:ring-gray-500">{{ old('description', $brand->description) }}</textarea>
        <x-input-error :messages="$errors->get('description')" />
    </div>

    <div class="grid grid-cols-2 gap-4">
        <div>
            <x-label for="country">Quốc gia</x-label>
            <x-input id="country" name="country" value="{{ old('country', $brand->country) }}" class="mt-1" />
            <x-input-error :messages="$errors->get('country')" />
        </div>
        <div>
            <x-label for="logo_path">Đường dẫn logo</x-label>
            <x-input id="logo_path" name="logo_path" value="{{ old('logo_path', $brand->logo_path) }}" class="mt-1" />
            <x-input-error :messages="$errors->get('logo_path')" />
        </div>
    </div>

    <div>
        <label class="flex items-center gap-2 text-sm text-gray-700">
            <input type="checkbox" name="is_active" value="1"
                   @checked(old('is_active', $brand->is_active ?? true))
                   class="rounded border-gray-300 text-gray-900 focus:ring-gray-500">
            Đang hoạt động
        </label>
        <x-input-error :messages="$errors->get('is_active')" />
    </div>

    <div class="flex items-center gap-2">
        <x-button type="submit">Lưu</x-button>
        <a href="{{ route('admin.brands.index') }}" class="text-sm text-gray-600 hover:underline">Hủy</a>
    </div>
</form>
