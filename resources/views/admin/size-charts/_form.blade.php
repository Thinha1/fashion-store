@php
    $route ??= route('admin.size-charts.store');
    $method ??= 'POST';
    $initial = [
        'columns' => array_values(old('columns', $chart->columns ?? [])),
        'rows' => array_values(old('rows', $chart->rows ?? [])),
        'sizes' => \App\Models\ProductVariant::SIZES,
    ];
    $fieldClass = 'block w-full rounded-md border border-gray-300 px-2 py-1.5 text-sm shadow-sm focus:border-gray-500 focus:outline-none focus:ring-1 focus:ring-gray-500';
@endphp

<x-admin.form-errors :messages="$errors->all()" />

<form method="POST" action="{{ $route }}" class="admin-form space-y-5" x-data="sizeChartForm({{ Js::from($initial) }})">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <div class="admin-form-heading"><x-icon name="table" /><div>
        <h2>Áp dụng cho</h2>
        <p>Sản phẩm tự dùng bảng khớp nhất: đúng thương hiệu trước, rồi danh mục gần nhất. Để "Mọi…" nếu bảng dùng chung.</p>
    </div></div>

    <div>
        <x-label for="name">Tên bảng size</x-label>
        <x-input id="name" name="name" value="{{ old('name', $chart->name) }}" required maxlength="120" placeholder="VD: Coolmate – Áo nam" class="mt-1" />
        <x-input-error :messages="$errors->get('name')" />
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <x-label for="brand_id">Thương hiệu</x-label>
            <select id="brand_id" name="brand_id" class="{{ $fieldClass }} mt-1 py-2">
                <option value="">Mọi thương hiệu</option>
                @foreach ($brands as $brand)
                    <option value="{{ $brand->id }}" @selected((int) old('brand_id', $chart->brand_id) === $brand->id)>{{ $brand->name }}</option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('brand_id')" />
        </div>
        <div>
            <x-label for="category_id">Danh mục (gồm cả danh mục con)</x-label>
            <select id="category_id" name="category_id" class="{{ $fieldClass }} mt-1 py-2">
                <option value="">Mọi danh mục</option>
                @foreach ($categories as $id => $path)
                    <option value="{{ $id }}" @selected((int) old('category_id', $chart->category_id) === $id)>{{ $path }}</option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('category_id')" />
        </div>
    </div>

    <div class="admin-form-heading"><x-icon name="layers" /><div>
        <h2>Bảng số đo</h2>
        <p>Chiều cao và cân nặng dùng để gợi ý size cho khách; các cột số đo khác (ngực, eo, dài…) chỉ để hiển thị.</p>
    </div></div>

    <div class="size-chart-grid overflow-x-auto rounded-lg border border-gray-200">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-left text-xs font-medium text-gray-600">
                <tr>
                    <th class="px-2 py-2">Size</th>
                    <th class="px-2 py-2" colspan="2">Chiều cao (cm)</th>
                    <th class="px-2 py-2" colspan="2">Cân nặng (kg)</th>
                    <template x-for="(column, columnIndex) in columns" :key="'c' + columnIndex">
                        <th class="px-2 py-2">
                            <div class="flex items-center gap-1">
                                <input type="text" :name="`columns[${columnIndex}]`" x-model="columns[columnIndex]" maxlength="40"
                                       placeholder="Tên cột" aria-label="Tên cột số đo" class="{{ $fieldClass }} min-w-28 font-medium">
                                <button type="button" class="text-gray-400 hover:text-red-600" x-on:click="removeColumn(columnIndex)" aria-label="Xóa cột">
                                    <x-icon name="close" class="size-3.5" />
                                </button>
                            </div>
                        </th>
                    </template>
                    <th class="px-2 py-2"><span class="sr-only">Xóa dòng</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <template x-for="(row, rowIndex) in rows" :key="'r' + rowIndex">
                    <tr>
                        <td class="px-2 py-1.5">
                            <select :name="`rows[${rowIndex}][size]`" x-model="row.size" aria-label="Size" class="{{ $fieldClass }} min-w-20">
                                <template x-for="size in sizes" :key="size"><option :value="size" x-text="size" :selected="size === row.size"></option></template>
                            </select>
                        </td>
                        <template x-for="key in ['height_min', 'height_max', 'weight_min', 'weight_max']" :key="key">
                            <td class="px-1 py-1.5">
                                <input type="number" min="10" max="250" :name="`rows[${rowIndex}][${key}]`" x-model="row[key]"
                                       :placeholder="key.endsWith('min') ? 'từ' : 'đến'" :aria-label="key" class="{{ $fieldClass }} min-w-16">
                            </td>
                        </template>
                        <template x-for="(column, columnIndex) in columns" :key="'v' + columnIndex">
                            <td class="px-1 py-1.5">
                                <input type="text" maxlength="20" :name="`rows[${rowIndex}][values][${columnIndex}]`" x-model="row.values[columnIndex]"
                                       placeholder="VD: 96-100" :aria-label="column || 'Số đo'" class="{{ $fieldClass }} min-w-24">
                            </td>
                        </template>
                        <td class="px-2 py-1.5 text-right">
                            <button type="button" class="text-gray-400 hover:text-red-600 disabled:opacity-30" x-on:click="removeRow(rowIndex)"
                                    :disabled="rows.length === 1" aria-label="Xóa dòng">
                                <x-icon name="delete" class="size-4" />
                            </button>
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>
    <div class="flex flex-wrap gap-2">
        <button type="button" class="admin-action admin-action-secondary" x-on:click="addRow()" :disabled="rows.length >= sizes.length">
            <x-icon name="plus" class="size-4" /> Thêm dòng
        </button>
        <button type="button" class="admin-action admin-action-secondary" x-on:click="addColumn()" :disabled="columns.length >= 8">
            <x-icon name="plus" class="size-4" /> Thêm cột số đo
        </button>
    </div>
    @foreach (['rows', 'rows.*', 'columns.*'] as $key)
        <x-input-error :messages="$errors->get($key)" />
    @endforeach

    <div>
        <x-label for="note">Ghi chú cho khách</x-label>
        <textarea id="note" name="note" rows="2" maxlength="1000" placeholder="VD: Nếu ở giữa hai size, chọn size lớn hơn để mặc thoải mái."
                  class="{{ $fieldClass }} mt-1 py-2">{{ old('note', $chart->note) }}</textarea>
        <x-input-error :messages="$errors->get('note')" />
    </div>

    <x-admin.form-actions :cancel="route('admin.size-charts.index')" :label="$chart->exists ? 'Lưu bảng size' : 'Tạo bảng size'" />
</form>
