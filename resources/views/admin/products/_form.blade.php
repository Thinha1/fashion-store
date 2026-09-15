@php($route = $route ?? route('admin.products.store'))
@php($method = $method ?? 'POST')
@php($variants = $variants ?? collect())
@php($images = $images ?? collect())

<x-admin.form-errors :messages="$errors->all()" />

<form method="POST" action="{{ $route }}" enctype="multipart/form-data" class="admin-form space-y-6">
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
            <x-button type="button" id="add-variant-row" variant="secondary"><x-icon name="plus" class="size-4" /> Thêm biến thể</x-button>
        </div>
        <p class="text-xs text-gray-500">Mỗi lựa chọn size và màu là một biến thể, có mã SKU riêng. Để trống giá để dùng giá cơ bản.</p>

        @php($variantIndex = 0)
        <div id="variants-list" class="space-y-3">
            @foreach ($variants as $variant)
                @php($rowIndex = $variant->exists ? $variant->id : $variantIndex)
                <div data-variant-row class="admin-variant-row">
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
                    <div class="absolute right-2 top-2">
                        <button type="button" class="remove-variant-row flex size-8 items-center justify-center rounded-lg text-gray-500 hover:bg-red-50 hover:text-red-700" title="Xóa biến thể" aria-label="Xóa biến thể"><x-icon name="close" class="size-4" /></button>
                    </div>
                </div>
                @php($variantIndex++)
            @endforeach
        </div>

        {{-- Template cho 1 dòng biến thể mới — clone qua JS thay vì dựng chuỗi
             HTML (tránh lỗi escape như đã gặp ở phiếu nhập). Index dùng tiền
             tố "new-" (không phải số) để không bao giờ trùng với id thật của
             biến thể đã có — nếu trùng, controller sẽ hiểu nhầm là sửa biến
             thể cũ thay vì tạo mới. --}}
        <template id="variant-row-template">
            <div data-variant-row class="admin-variant-row">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Size</label>
                    <input type="text" name="variants[__INDEX__][size]" required
                           class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Màu</label>
                    <input type="text" name="variants[__INDEX__][color]" required
                           class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">SKU</label>
                    <input type="text" name="variants[__INDEX__][sku]" required
                           class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Giá (VNĐ)</label>
                    <input type="number" name="variants[__INDEX__][price]" min="0" step="0.01"
                           class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Tồn kho</label>
                    <input type="number" name="variants[__INDEX__][stock_quantity]" value="0" min="0" required
                           class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Cảnh báo tồn</label>
                    <input type="number" name="variants[__INDEX__][low_stock_threshold]" value="5" min="0" required
                           class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                </div>
                <div class="absolute right-2 top-2">
                    <button type="button" class="remove-variant-row flex size-8 items-center justify-center rounded-lg text-gray-500 hover:bg-red-50 hover:text-red-700" title="Xóa biến thể" aria-label="Xóa biến thể"><x-icon name="close" class="size-4" /></button>
                </div>
            </div>
        </template>

        <script>
            (function () {
                var nextNewIndex = 0;
                var template = document.getElementById('variant-row-template');
                var list = document.getElementById('variants-list');

                document.getElementById('add-variant-row').addEventListener('click', function () {
                    var row = template.content.cloneNode(true);
                    row.querySelectorAll('[name]').forEach(function (el) {
                        el.name = el.name.replace('__INDEX__', 'new-' + nextNewIndex);
                    });
                    list.appendChild(row);
                    nextNewIndex++;
                });

                // Delegated so it works for both server-rendered rows and
                // freshly cloned ones without re-binding listeners each time.
                list.addEventListener('click', function (e) {
                    var button = e.target.closest('.remove-variant-row');
                    if (button) {
                        button.closest('[data-variant-row]').remove();
                    }
                });
            })();
        </script>

        <x-input-error :messages="$errors->get('variants')" />
    </section>

    {{-- Images --}}
    <section class="space-y-4">
        <h2 class="text-base font-semibold text-gray-900">Ảnh sản phẩm</h2>
        <p class="text-xs text-gray-500">Chọn nhiều ảnh cùng lúc bằng Ctrl/Shift. JPG, PNG hoặc WebP, tối đa 4 MB mỗi ảnh.</p>

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
                    <img :src="src" alt="Ảnh sản phẩm mới chọn" class="aspect-square w-full rounded-md border border-gray-200 object-cover ring-2 ring-gray-900">
                </template>
            </div>
        </div>
    </section>

    <x-admin.form-actions :cancel="route('admin.products.index')" label="Lưu sản phẩm" />
</form>
