@php
    $route = $route ?? route('admin.products.store');
    $method = $method ?? 'POST';
    $images = $images ?? collect();
    $variantRows = session()->hasOldInput() ? collect(old('variants', [])) : ($variants ?? collect())->keyBy('id');
@endphp
<x-admin.form-errors :messages="$errors->all()" />

<form method="POST" action="{{ $route }}" enctype="multipart/form-data" class="admin-form space-y-6" x-data="productForm({{ Js::from(old('removed_images', [])) }})">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    {{-- Product basic info --}}
    <section class="space-y-4">
        <h2 class="text-base font-semibold text-gray-900">Thông tin sản phẩm</h2>

        <div>
            <x-label for="name">Tên sản phẩm</x-label>
            <x-input id="name" name="name" value="{{ old('name', $product->name) }}" required class="mt-1" />
            <x-input-error :messages="$errors->get('name')" />

        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <x-label for="category_id">Danh mục</x-label>
                <select id="category_id" name="category_id" required
                        class="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-gray-500 focus:outline-none focus:ring-1 focus:ring-gray-500">
                    <option value="">— Chọn danh mục —</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected((int) old('category_id', $product->category_id) === $category->id)>
                            {{ $category->name }}
                        </option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('category_id')" />
            </div>

            <div>
                <x-image-select id="brand_id" name="brand_id" label="Thương hiệu" placeholder="— Chọn thương hiệu —" required
                    :value="old('brand_id', $product->brand_id)"
                    :options="$brands->map(fn ($brand) => ['value' => $brand->id, 'label' => $brand->name, 'image' => $brand->logo_path ? \Illuminate\Support\Facades\Storage::disk('s3')->url($brand->logo_path) : null])->all()" />
                <x-input-error :messages="$errors->get('brand_id')" />
            </div>
        </div>

        <div>
            <x-label for="description">Mô tả</x-label>
            <textarea id="description" name="description" rows="4"
                      class="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-gray-500 focus:outline-none focus:ring-1 focus:ring-gray-500">{{ old('description', $product->description) }}</textarea>
            <x-input-error :messages="$errors->get('description')" />
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <x-label for="base_price">Giá cơ bản (VNĐ)</x-label>
                <x-currency-input id="base_price" name="base_price" :value="old('base_price', $product->base_price)" required class="mt-1" />
                <x-input-error :messages="$errors->get('base_price')" />
            </div>

            <div class="flex flex-col justify-end">
                <input type="hidden" name="status" value="archived">
                <label class="flex min-h-11 items-center gap-2 text-sm text-gray-700" for="status">
                    <input id="status" type="checkbox" name="status" value="active"
                           @checked(old('status', $product->status) === 'active')
                           class="rounded border-gray-300 text-brand focus:ring-brand">
                    Đang kinh doanh
                </label>
                <x-input-error :messages="$errors->get('status')" />
            </div>

        </div>
    </section>

    {{-- Variants --}}
    <section class="space-y-4">
        <div class="flex items-center justify-between">
            <h2 class="text-base font-semibold text-gray-900">Biến thể</h2>
            <x-button type="button" id="add-variant-row" x-on:click="addVariant()" variant="secondary"><x-icon name="plus" class="size-4" /> Thêm biến thể</x-button>
        </div>
        <p class="text-xs text-gray-500">Mỗi lựa chọn size và màu là một biến thể, có mã SKU riêng. Để trống giá để dùng giá cơ bản.</p>

        <div id="variants-list" class="space-y-3">
            @foreach ($variantRows as $rowKey => $variant)
                <x-admin.product-variant-row :row-key="$rowKey" :variant="$variant" :images="$images->where('product_variant_id', $rowKey)" />
            @endforeach
        </div>
        <template id="variant-row-template">
            <x-admin.product-variant-row row-key="__INDEX__" />
        </template>
        <x-input-error :messages="$errors->get('variants')" />
    </section>

    {{-- Images --}}
    <section class="space-y-4">
        <h2 class="text-base font-semibold text-gray-900">Ảnh chung</h2>
        <p class="text-xs text-gray-500">Chọn nhiều ảnh cùng lúc bằng Ctrl/Shift. JPG, PNG hoặc WebP, tối đa 4 MB mỗi ảnh.</p>

        <x-admin.product-image-preview :images="$images->whereNull('product_variant_id')" />
        <x-admin.image-upload id="images" name="images[]" label="Thêm ảnh chung" error-key="images" />
        <p class="text-xs text-gray-500">Biến thể chưa có ảnh riêng sẽ hiển thị ảnh chung.</p>
    </section>
    <template x-for="id in removedImages" :key="id">
        <input type="hidden" name="removed_images[]" :value="id">
    </template>
    <div x-show="removedImages.length" x-cloak class="flex flex-wrap items-center gap-3 text-sm text-gray-600" role="status">
        <span x-text="removedImages.length + ' ảnh sẽ được xóa khi lưu.'"></span>
        <button type="button" x-on:click="removedImages = []" class="font-semibold text-brand underline underline-offset-4">Hoàn tác xóa ảnh</button>
    </div>
    <x-admin.form-actions :cancel="route('admin.products.index')" label="Lưu sản phẩm" />
</form>
