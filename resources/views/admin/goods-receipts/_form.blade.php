@php($route = $route ?? route('admin.goods-receipts.store'))
@php($method = $method ?? 'POST')
@php($items = $items ?? collect())

<form method="POST" action="{{ $route }}" class="space-y-6">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <x-label for="supplier_id">Nhà cung cấp</x-label>
            <select id="supplier_id" name="supplier_id" required
                    class="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-gray-500 focus:outline-none focus:ring-1 focus:ring-gray-500">
                <option value="">— Chọn nhà cung cấp —</option>
                @foreach ($suppliers as $supplier)
                    <option value="{{ $supplier->id }}" @selected((int) old('supplier_id', $receipt->supplier_id) === $supplier->id)>
                        {{ $supplier->name }}
                    </option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('supplier_id')" />
        </div>

        <div>
            <x-label for="notes">Ghi chú</x-label>
            <x-input id="notes" name="notes" value="{{ old('notes', $receipt->notes) }}" class="mt-1" />
            <x-input-error :messages="$errors->get('notes')" />
        </div>
    </div>

    <section class="space-y-4">
        <h2 class="text-base font-semibold text-gray-900">Dòng hàng</h2>
        <p class="text-xs text-gray-500">Mỗi dòng là một biến thể sản phẩm. Không được lặp lại biến thể trong cùng một phiếu.</p>

        @php($itemIndex = 0)
        <div class="space-y-3">
            @foreach ($items as $item)
                @php($rowIndex = $item->exists ? $item->id : $itemIndex)
                <div class="grid grid-cols-1 gap-3 rounded-md border border-gray-200 bg-gray-50 p-4 sm:grid-cols-4">
                    @if ($item->exists)
                        <input type="hidden" name="items[{{ $rowIndex }}][id]" value="{{ $item->id }}">
                    @endif
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Biến thể</label>
                        <select name="items[{{ $rowIndex }}][product_variant_id]" required
                                class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                            <option value="">— Chọn biến thể —</option>
                            @foreach (\App\Models\ProductVariant::query()->where('is_active', true)->with('product:id,name')->get() as $variant)
                                <option value="{{ $variant->id }}" @selected((int) old('items.'.$itemIndex.'.product_variant_id', $item->product_variant_id ?? 0) === $variant->id)>
                                    {{ $variant->product->name }} — {{ $variant->size }} / {{ $variant->color }} ({{ $variant->sku }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Số lượng</label>
                        <input type="number" name="items[{{ $rowIndex }}][quantity]" value="{{ old('items.'.$itemIndex.'.quantity', $item->quantity ?? 1) }}" min="1" required
                               class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Giá nhập (VNĐ)</label>
                        <input type="number" name="items[{{ $rowIndex }}][cost_price]" value="{{ old('items.'.$itemIndex.'.cost_price', $item->cost_price ?? 0) }}" min="0" step="0.01" required
                               class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Thành tiền</label>
                        <div class="mt-1 rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700">
                            {{ number_format((float) old('items.'.$itemIndex.'.quantity', $item->quantity ?? 1) * (float) old('items.'.$itemIndex.'.cost_price', $item->cost_price ?? 0), 0) }} ₫
                        </div>
                    </div>
                </div>
                @php($itemIndex++)
            @endforeach
        </div>

        <button type="button" class="text-sm text-gray-700 hover:underline"
                onclick="
                    var i = {{ $itemIndex }};
                    var selectOptions = @foreach (\App\Models\ProductVariant::query()->where('is_active', true)->with('product:id,name')->get() as $v)
                        '&lt;option value={{ $v->id }}&gt;{{ $v->product->name }} — {{ $v->size }} / {{ $v->color }} ({{ $v->sku }})&lt;/option&gt;';
                    @endforeach;
                    var html = '&lt;div class=\\"grid grid-cols-1 gap-3 rounded-md border border-gray-200 bg-gray-50 p-4 sm:grid-cols-4\\"&gt;'
                        + '&lt;div&gt;&lt;label class=\\"block text-sm font-medium text-gray-700\\"&gt;Biến thể&lt;/label&gt;&lt;select name=\\"items['.i.'][product_variant_id]\\" required class=\\"mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm\\">&lt;option value=\\"\\"&gt;— Chọn biến thể —&lt;/option&gt;'.selectOptions+'&lt;/select&gt;&lt;/div&gt;'
                        + '&lt;div&gt;&lt;label class=\\"block text-sm font-medium text-gray-700\\"&gt;Số lượng&lt;/label&gt;&lt;input type=\\"number\\" name=\\"items['.i.'][quantity]\\" value=\\"1\\" min=\\"1\\" required class=\\"mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm\\"&gt;&lt;/div&gt;'
                        + '&lt;div&gt;&lt;label class=\\"block text-sm font-medium text-gray-700\\"&gt;Giá nhập (VNĐ)&lt;/label&gt;&lt;input type=\\"number\\" name=\\"items['.i.'][cost_price]\\" value=\\"0\\" min=\\"0\\" step=\\"0.01\\" required class=\\"mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm\\"&gt;&lt;/div&gt;'
                        + '&lt;div&gt;&lt;label class=\\"block text-sm font-medium text-gray-700\\"&gt;Thành tiền&lt;/label&gt;&lt;div class=\\"mt-1 rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700\\"&gt;0 ₫&lt;/div&gt;&lt;/div&gt;'
                        + '&lt;/div&gt;';
                    document.getElementById('items-list').insertAdjacentHTML('beforeend', html);
                "
        >+ Thêm dòng hàng</button>
        <div id="items-list" class="space-y-3"></div>
        <x-input-error :messages="$errors->get('items')" />
    </section>

    <div class="flex items-center gap-2 border-t border-gray-200 pt-4">
        <x-button type="submit">Lưu phiếu nhập</x-button>
        <a href="{{ route('admin.goods-receipts.index') }}" class="text-sm text-gray-600 hover:underline">Hủy</a>
    </div>
</form>
