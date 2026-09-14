@php($route = $route ?? route('admin.products.store'))
@php($method = $method ?? 'POST')
@php($variants = $variants ?? collect())
@php($images = $images ?? collect())
@php($existingVariantCount = $variants->count())

<form method="POST" action="{{ $route }}" enctype="multipart/form-data" class="space-y-6" x-data="{ variantCount: {{ $existingVariantCount }} }">
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
            @if ($product->exists)
                <p class="mt-1 text-xs text-gray-500">Slug: <code>{{ $product->slug }}</code> (sinh tự động từ tên lúc tạo, không đổi sau đó).</p>
            @else
                <p class="mt-1 text-xs text-gray-500">Slug (định danh URL) sẽ được sinh tự động từ tên.</p>
            @endif
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
                <x-label for="brand_id">Thương hiệu</x-label>
                <select id="brand_id" name="brand_id" required
                        class="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-gray-500 focus:outline-none focus:ring-1 focus:ring-gray-500">
                    <option value="">— Chọn thương hiệu —</option>
                    @foreach ($brands as $brand)
                        <option value="{{ $brand->id }}" @selected((int) old('brand_id', $product->brand_id) === $brand->id)>
                            {{ $brand->name }}
                        </option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('brand_id')" />
            </div>
        </div>

        <div>
            <x-label for="description">Mô tả</x-label>
            <textarea id="description" name="description" rows="4"
                      class="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-gray-500 focus:outline-none focus:ring-1 focus:ring-gray-500">{{ old('description', $product->description) }}</textarea>
            <x-input-error :messages="$errors->get('description')" />
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div>
                <x-label for="base_price">Giá cơ bản (VNĐ)</x-label>
                <x-input id="base_price" type="number" name="base_price" value="{{ old('base_price', $product->base_price) }}" min="0" step="0.01" required class="mt-1" />
                <x-input-error :messages="$errors->get('base_price')" />
            </div>

            <div>
                <x-label for="status">Trạng thái</x-label>
                <select id="status" name="status" required
                        class="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-gray-500 focus:outline-none focus:ring-1 focus:ring-gray-500">
                    <option value="draft" @selected(old('status', $product->status) === 'draft')>Bản nháp</option>
                    <option value="active" @selected(old('status', $product->status) === 'active')>Hoạt động</option>
                    <option value="archived" @selected(old('status', $product->status) === 'archived')>Lưu trữ</option>
                </select>
                <x-input-error :messages="$errors->get('status')" />
            </div>

            <div class="flex items-end pb-2">
                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" name="is_featured" value="1"
                           @checked(old('is_featured', $product->is_featured ?? false))
                           class="rounded border-gray-300 text-gray-900 focus:ring-gray-500">
                    Sản phẩm nổi bật
                </label>
            </div>
        </div>
    </section>

    {{-- Variants --}}
    <section class="space-y-4">
        <div class="flex items-center justify-between">
            <h2 class="text-base font-semibold text-gray-900">Biến thể</h2>
            <button type="button"
                    @click="variantCount += 1"
                    class="text-sm text-gray-700 hover:underline">
                + Thêm biến thể
            </button>
        </div>
        <p class="text-xs text-gray-500">Size/màu được chuẩn hóa hoa-thường trước khi lưu. Mỗi SKU phải duy nhất.</p>

        @php($variantIndex = 0)
        <div class="space-y-3">
            @foreach ($variants as $variant)
                @php($rowIndex = $variant->exists ? $variant->id : $variantIndex)
                <div class="grid grid-cols-1 gap-3 rounded-md border border-gray-200 bg-gray-50 p-4 sm:grid-cols-6">
                    @if ($variant->exists)
                        <input type="hidden" name="variants[{{ $rowIndex }}][id]" value="{{ $variant->id }}">
                    @endif
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Size</label>
                        <input type="text" name="variants[{{ $rowIndex }}][size]" value="{{ old('variants.'.$variantIndex.'.size', $variant->size ?? '') }}" required
                               class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Màu</label>
                        <input type="text" name="variants[{{ $rowIndex }}][color]" value="{{ old('variants.'.$variantIndex.'.color', $variant->color ?? '') }}" required
                               class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">SKU</label>
                        <input type="text" name="variants[{{ $rowIndex }}][sku]" value="{{ old('variants.'.$variantIndex.'.sku', $variant->sku ?? '') }}" required
                               class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Giá (VNĐ)</label>
                        <input type="number" name="variants[{{ $rowIndex }}][price]" value="{{ old('variants.'.$variantIndex.'.price', $variant->price ?? '') }}" min="0" step="0.01"
                               class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Tồn kho</label>
                        <input type="number" name="variants[{{ $rowIndex }}][stock_quantity]" value="{{ old('variants.'.$variantIndex.'.stock_quantity', $variant->stock_quantity ?? 0) }}" min="0" required
                               class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Cảnh báo tồn</label>
                        <input type="number" name="variants[{{ $rowIndex }}][low_stock_threshold]" value="{{ old('variants.'.$variantIndex.'.low_stock_threshold', $variant->low_stock_threshold ?? 5) }}" min="0" required
                               class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                    </div>
                </div>
                @php($variantIndex++)
            @endforeach
        </div>

        {{-- Alpine.js template for new variant rows --}}
        <template x-teleport="body"></template>
        <x-input-error :messages="$errors->get('variants')" />
    </section>

    {{-- Images --}}
    <section class="space-y-4">
        <h2 class="text-base font-semibold text-gray-900">Ảnh sản phẩm</h2>
        <p class="text-xs text-gray-500">JPG, JPEG, PNG hoặc WebP, tối đa 4MB mỗi ảnh.</p>

        @if ($images->count())
            <div class="grid grid-cols-3 gap-4 sm:grid-cols-6">
                @foreach ($images as $image)
                    <figure class="overflow-hidden rounded-md border border-gray-200">
                        <img src="{{ \Illuminate\Support\Facades\Storage::disk('s3')->url($image->path) }}" alt="{{ $image->alt_text }}" class="aspect-square w-full object-cover">
                        <figcaption class="px-2 py-1 text-xs text-gray-500">
                            @if ($image->is_primary) <span class="text-green-600">Ảnh chính</span> @endif
                        </figcaption>
                    </figure>
                @endforeach
            </div>
        @endif

        <div x-data="{ previews: [] }">
            <x-label for="images">Thêm ảnh</x-label>
            <input id="images" type="file" name="images[]" multiple accept="image/jpeg,image/png,image/webp"
                   class="block w-full text-sm text-gray-700 file:mr-4 file:rounded-md file:border-0 file:bg-gray-900 file:px-4 file:py-2 file:text-sm file:font-medium file:text-white hover:file:bg-gray-700"
                   x-on:change="
                       previews = [];
                       Array.from($event.target.files).forEach((file) => {
                           const reader = new FileReader();
                           reader.onload = () => { previews.push(reader.result) };
                           reader.readAsDataURL(file);
                       });
                   ">
            <x-input-error :messages="$errors->get('images')" />

            <template x-if="previews.length">
                <p class="mt-3 text-xs font-medium text-gray-500">Ảnh mới chọn (chưa lưu):</p>
            </template>
            <div class="mt-2 grid grid-cols-3 gap-4 sm:grid-cols-6" x-show="previews.length">
                <template x-for="(src, index) in previews" :key="index">
                    <img :src="src" class="aspect-square w-full rounded-md border border-gray-200 object-cover ring-2 ring-gray-900">
                </template>
            </div>
        </div>
    </section>

    <div class="flex items-center gap-2 border-t border-gray-200 pt-4">
        <x-button type="submit">Lưu sản phẩm</x-button>
        <a href="{{ route('admin.products.index') }}" class="text-sm text-gray-600 hover:underline">Hủy</a>
    </div>
</form>
