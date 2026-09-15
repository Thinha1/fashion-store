@php($route = $route ?? 'admin.brands.store')
@php($method = $method ?? 'POST')

<x-admin.form-errors :messages="$errors->all()" />

<form method="POST" action="{{ $route }}" enctype="multipart/form-data" class="admin-form admin-form-simple space-y-5">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <div class="admin-form-heading"><x-icon name="tag" /><div><h2>Thông tin thương hiệu</h2><p>Điền thông tin và chọn trạng thái hiển thị.</p></div></div>

    <div>
        <x-label for="name">Tên thương hiệu</x-label>
        <x-input id="name" name="name" value="{{ old('name', $brand->name) }}" required class="mt-1" />
        <x-input-error :messages="$errors->get('name')" />
    </div>

    <div>
        <x-label for="description">Mô tả</x-label>
        <textarea id="description" name="description" rows="3"
                  class="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-gray-500 focus:outline-none focus:ring-1 focus:ring-gray-500">{{ old('description', $brand->description) }}</textarea>
        <x-input-error :messages="$errors->get('description')" />
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <x-label for="country">Quốc gia</x-label>
            <x-input id="country" name="country" value="{{ old('country', $brand->country) }}" class="mt-1" />
            <x-input-error :messages="$errors->get('country')" />
        </div>
        <div x-data="{ preview: null }">
            <x-label for="logo">Logo</x-label>

            {{-- Ảnh đang chọn (client-side, chưa lưu) — ưu tiên hiện thay cho logo cũ khi có --}}
            <template x-if="preview">
                <img :src="preview" alt="Xem trước logo mới" class="mt-1 mb-2 h-16 w-16 rounded object-cover ring-2 ring-gray-900">
            </template>
            @if ($brand->logo_path)
                <img x-show="!preview" src="{{ \Illuminate\Support\Facades\Storage::disk('s3')->url($brand->logo_path) }}" alt="Logo hiện tại" class="mt-1 mb-2 h-16 w-16 rounded object-cover">
            @endif

            <input id="logo" name="logo" type="file" accept="image/*" class="mt-1 block w-full text-sm text-gray-600 file:mr-3 file:rounded-md file:border-0 file:bg-gray-900 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-white hover:file:bg-gray-700"
                   x-on:change="
                       const file = $event.target.files[0];
                       if (! file) { preview = null; return; }
                       const reader = new FileReader();
                       reader.onload = () => { preview = reader.result };
                       reader.readAsDataURL(file);
                   " />
            <x-input-error :messages="$errors->get('logo')" />
            <p class="mt-1 text-xs text-gray-500">Ảnh (jpg/png/webp...), tối đa 2MB. Bỏ trống để giữ logo hiện tại.</p>
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

    <x-admin.form-actions :cancel="route('admin.brands.index')" label="Lưu thương hiệu" />
</form>
