@php($route = $route ?? route('admin.goods-receipts.store'))
@php($method = $method ?? 'POST')
@php($items = $items ?? collect())
@php($items = session()->hasOldInput() ? collect(old('items', []))->map(fn ($row) => new \App\Models\GoodsReceiptItem($row)) : $items->values())
@php($variants = \App\Models\ProductVariant::query()->where('is_active', true)->with('product:id,name')->get())

<x-admin.form-errors :messages="$errors->all()" />

<form method="POST" action="{{ $route }}" class="admin-form space-y-6">
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
        <div id="items-list" class="space-y-3">
            @foreach ($items as $rowIndex => $item)
                <div data-item-row class="admin-receipt-row">
                    <x-button type="button" variant="secondary" data-remove-item class="absolute top-3 right-3 min-h-9 px-2.5 hover:border-red-200 hover:bg-red-50 hover:text-red-600" aria-label="Xóa dòng hàng" title="Xóa dòng hàng"><x-icon name="close" class="size-4" /></x-button>
                    @if ($item->exists)
                        <input type="hidden" name="items[{{ $rowIndex }}][id]" value="{{ $item->id }}">
                    @endif
                    <div>
                        <label for="item-{{ $rowIndex }}-product_variant_id" class="block text-sm font-medium text-gray-700">Biến thể</label>
                        <select id="item-{{ $rowIndex }}-product_variant_id" name="items[{{ $rowIndex }}][product_variant_id]" required
                                class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                            <option value="">— Chọn biến thể —</option>
                            @foreach ($variants as $variant)
                                <option value="{{ $variant->id }}" @selected((int) old('items.'.$rowIndex.'.product_variant_id', $item->product_variant_id ?? 0) === $variant->id)>
                                    {{ $variant->product->name }} — {{ $variant->size }} / {{ $variant->color }} ({{ $variant->sku }})
                                </option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('items.'.$rowIndex.'.product_variant_id')" />
                    </div>
                    <div>
                        <label for="item-{{ $rowIndex }}-quantity" class="block text-sm font-medium text-gray-700">Số lượng</label>
                        <input type="number" id="item-{{ $rowIndex }}-quantity" name="items[{{ $rowIndex }}][quantity]" value="{{ old('items.'.$rowIndex.'.quantity', $item->quantity ?? 1) }}" min="1" required
                               class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                        <x-input-error :messages="$errors->get('items.'.$rowIndex.'.quantity')" />
                    </div>
                    <div>
                        <label for="item-{{ $rowIndex }}-cost_price" class="block text-sm font-medium text-gray-700">Giá nhập (VNĐ)</label>
                        <input type="number" id="item-{{ $rowIndex }}-cost_price" name="items[{{ $rowIndex }}][cost_price]" value="{{ old('items.'.$rowIndex.'.cost_price', $item->cost_price ?? 0) }}" min="0" step="0.01" required
                               class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                        <x-input-error :messages="$errors->get('items.'.$rowIndex.'.cost_price')" />
                    </div>
                    <div>
                        <p class="block text-sm font-medium text-gray-700">Thành tiền</p>
                        <div class="mt-1 rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700">
                            {{ number_format((float) old('items.'.$rowIndex.'.quantity', $item->quantity ?? 1) * (float) old('items.'.$rowIndex.'.cost_price', $item->cost_price ?? 0), 0) }} ₫
                        </div>
                    </div>
                </div>
                @php($itemIndex = max($itemIndex, (int) $rowIndex + 1))
            @endforeach
        </div>

        {{-- Template cho 1 dòng hàng mới — trình duyệt tự parse thành DOM thật,
             không cần dựng chuỗi HTML bằng JS (tránh lỗi escape lồng nhau). --}}
        <template id="item-row-template">
            <div data-item-row class="admin-receipt-row">
                <x-button type="button" variant="secondary" data-remove-item class="absolute top-3 right-3 min-h-9 px-2.5 hover:border-red-200 hover:bg-red-50 hover:text-red-600" aria-label="Xóa dòng hàng" title="Xóa dòng hàng"><x-icon name="close" class="size-4" /></x-button>
                <div>
                    <label for="item-__INDEX__-product_variant_id" class="block text-sm font-medium text-gray-700">Biến thể</label>
                    <select id="item-__INDEX__-product_variant_id" name="items[__INDEX__][product_variant_id]" required
                            class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                        <option value="">— Chọn biến thể —</option>
                        @foreach ($variants as $v)
                            <option value="{{ $v->id }}">{{ $v->product->name }} — {{ $v->size }} / {{ $v->color }} ({{ $v->sku }})</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="item-__INDEX__-quantity" class="block text-sm font-medium text-gray-700">Số lượng</label>
                    <input type="number" id="item-__INDEX__-quantity" name="items[__INDEX__][quantity]" value="1" min="1" required
                           class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label for="item-__INDEX__-cost_price" class="block text-sm font-medium text-gray-700">Giá nhập (VNĐ)</label>
                    <input type="number" id="item-__INDEX__-cost_price" name="items[__INDEX__][cost_price]" value="0" min="0" step="0.01" required
                           class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <p class="block text-sm font-medium text-gray-700">Thành tiền</p>
                    <div class="mt-1 rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700">0 ₫</div>
                </div>
            </div>
        </template>

        <x-button type="button" id="add-item-row" variant="secondary"><x-icon name="plus" class="size-4" /> Thêm dòng hàng</x-button>
        <x-input-error :messages="$errors->get('items')" />

        <script>
            (function () {
                var nextIndex = {{ $itemIndex }};
                var template = document.getElementById('item-row-template');
                var list = document.getElementById('items-list');

                list.addEventListener('click', function (event) {
                    var button = event.target.closest('[data-remove-item]');
                    if (button) {
                        button.closest('[data-item-row]').remove();
                    }
                });

                document.getElementById('add-item-row').addEventListener('click', function () {
                    var row = template.content.cloneNode(true);
                    row.querySelectorAll('[name], [id], [for]').forEach(function (el) {
                        ['name', 'id', 'for'].forEach(function (attribute) {
                            if (el.hasAttribute(attribute)) {
                                el.setAttribute(attribute, el.getAttribute(attribute).replaceAll('__INDEX__', nextIndex));
                            }
                        });
                    });
                    list.appendChild(row);
                    nextIndex++;
                });
            })();
        </script>
    </section>

    <x-admin.form-actions :cancel="route('admin.goods-receipts.index')" label="Lưu phiếu nhập" />
</form>
