@php($route = $route ?? route('admin.categories.store'))
@php($method = $method ?? 'POST')

<x-admin.form-errors :messages="$errors->all()" />

<form method="POST" action="{{ $route }}" class="admin-form admin-form-simple space-y-5">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <div class="admin-form-heading"><x-icon name="layers" /><div><h2>Thông tin danh mục</h2><p>Điền thông tin và chọn trạng thái hiển thị.</p></div></div>

    <div>
        <x-label for="name">Tên danh mục</x-label>
        <x-input id="name" name="name" value="{{ old('name', $category->name) }}" required class="mt-1" />
        <x-input-error :messages="$errors->get('name')" />
    </div>

    <div>
        <x-label for="parent_id">Danh mục cha (tùy chọn)</x-label>
        <select id="parent_id" name="parent_id"
                class="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-gray-500 focus:outline-none focus:ring-1 focus:ring-gray-500">
            <option value="">— Danh mục gốc —</option>
            @foreach ($parents as $parent)
                <option value="{{ $parent->id }}" @selected((int) ($category->parent_id ?? old('parent_id')) === $parent->id)
                        @disabled($parent->id === $category->id)>
                    {{ $parent->name }}
                </option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('parent_id')" />
        @if ($category->exists)
            <p class="mt-1 text-xs text-gray-500">Danh mục "{{ $category->name }}" không thể chọn chính nó làm cha mẹ.</p>
        @endif
    </div>

    <div>
        <x-label for="description">Mô tả</x-label>
        <textarea id="description" name="description" rows="3"
                  class="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-gray-500 focus:outline-none focus:ring-1 focus:ring-gray-500">{{ old('description', $category->description) }}</textarea>
        <x-input-error :messages="$errors->get('description')" />
    </div>

    <div>
        <x-label for="sort_order">Thứ tự hiển thị</x-label>
        <x-input id="sort_order" type="number" name="sort_order" value="{{ old('sort_order', $category->sort_order ?? 0) }}" min="0" class="mt-1" />
        <x-input-error :messages="$errors->get('sort_order')" />
    </div>

    <div>
        <label class="flex items-center gap-2 text-sm text-gray-700">
            <input type="checkbox" name="is_active" value="1"
                   @checked(old('is_active', $category->is_active ?? true))
                   class="rounded border-gray-300 text-gray-900 focus:ring-gray-500">
            Đang hoạt động
        </label>
    </div>

    <x-admin.form-actions :cancel="route('admin.categories.index')" label="Lưu danh mục" />
</form>
