@props(['rowKey', 'variant' => [], 'images' => []])
@php
    $fields = [
        'color' => ['Màu', 'text', ''],
        'sku' => ['SKU', 'text', ''], 'price' => ['Giá (VNĐ)', 'number', ''],
        'stock_quantity' => ['Tồn kho', 'number', 0], 'low_stock_threshold' => ['Cảnh báo tồn', 'number', 5],
    ];
@endphp
<div data-variant-row data-variant-key="{{ $rowKey }}" class="admin-variant-row">
    <div>
        <label for="variant-{{ $rowKey }}-size" class="block text-sm font-medium text-gray-700">Size</label>
        <select id="variant-{{ $rowKey }}-size" name="variants[{{ $rowKey }}][size]" required
                class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
            <option value="">— Chọn size —</option>
            @foreach (\App\Models\ProductVariant::SIZES as $size)
                <option value="{{ $size }}" @selected(data_get($variant, 'size') === $size)>{{ $size }}</option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('variants.'.$rowKey.'.size')" />
    </div>
    @foreach ($fields as $field => [$label, $type, $default])
        <div>
            <label for="variant-{{ $rowKey }}-{{ $field }}" class="block text-sm font-medium text-gray-700">{{ $label }}</label>
            @if ($field === 'price')
                <x-currency-input :id="'variant-'.$rowKey.'-'.$field" :name="'variants['.$rowKey.']['.$field.']'"
                    :value="data_get($variant, $field, $default)" class="mt-1" />
            @else
            <input id="variant-{{ $rowKey }}-{{ $field }}" type="{{ $type }}" name="variants[{{ $rowKey }}][{{ $field }}]"
                   value="{{ data_get($variant, $field, $default) }}" required
                   @if ($type === 'number') min="0" step="1" @endif
                   class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
            @endif
            <x-input-error :messages="$errors->get('variants.'.$rowKey.'.'.$field)" />
        </div>
    @endforeach
    <input type="hidden" name="variants[{{ $rowKey }}][is_active]" value="{{ (int) data_get($variant, 'is_active', true) }}">
    <div class="col-span-full border-t border-gray-200 pt-3">
        <x-admin.product-image-preview :images="$images" />
        <x-admin.image-upload :id="'variant-'.$rowKey.'-images'" :name="'variants['.$rowKey.'][images][]'"
            label="Thêm ảnh cho biến thể" :error-key="'variants.'.$rowKey.'.images'" preview-alt="Ảnh biến thể mới chọn" />
    </div>
    <div class="absolute right-2 top-2">
        <button type="button" x-on:click="removeVariant($el)" class="remove-variant-row flex size-8 items-center justify-center rounded-lg text-gray-500 hover:bg-red-50 hover:text-red-700" title="Xóa biến thể" aria-label="Xóa biến thể"><x-icon name="close" class="size-4" /></button>
    </div>
</div>
