@props(['header' => [], 'paginator' => null, 'sortable' => [], 'sorting' => null])

<div x-data="dataTable(@js(in_array('Thao tác', $header, true)))" {{ $attributes->merge(['class' => 'admin-table-card overflow-hidden border border-gray-200 bg-white']) }}>
    <div x-cloak x-show="total > 0" class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 p-4">
        <label class="relative w-full sm:max-w-xs">
            <span class="sr-only">Tìm trong trang này</span>
            <i class="fa-solid fa-magnifying-glass absolute top-3.5 left-3.5 text-sm text-gray-400" aria-hidden="true"></i>
            <input type="search" x-model.debounce.150ms="query" placeholder="Tìm trong trang này…" class="field pl-10" />
        </label>
        <span class="text-xs text-gray-500" role="status"><span x-text="visible"></span> / <span x-text="total"></span> bản ghi trong trang</span>
    </div>
    <div tabindex="0" role="region" aria-label="Bảng @yield('title', 'dữ liệu')" class="data-table rounded-none border-0 shadow-none">
    <table>
        @if ($header)
            <thead>
                <tr>
                    @foreach ($header as $label)
                        @php
                            $column = $sortable[$label] ?? null;
                            $direction = $column ? $sorting?->directionFor($column) : null;
                        @endphp
                        <th scope="col" @class(['text-right' => $label === 'Thao tác']) @if ($column && $sorting) aria-sort="{{ match ($direction) { 'asc' => 'ascending', 'desc' => 'descending', default => 'none' } }}" @endif>
                            <x-table-sort :label="$label" :column="$column" :sorting="$sorting" />
                        </th>
                    @endforeach
                </tr>
            </thead>
        @endif
        <tbody x-ref="rows">
            {{ $slot }}
        </tbody>
    </table>
    </div>
    <div x-cloak x-show="query.trim() && visible === 0" class="px-5 py-10 text-center">
        <p class="text-sm font-medium text-gray-600">Không tìm thấy kết quả trong trang này.</p>
        <button type="button" x-on:click="query = ''" class="mt-3 text-sm font-semibold text-brand underline underline-offset-4">Xóa từ khóa</button>
    </div>
    @if ($paginator)
        <x-pagination :paginator="$paginator" />
    @endif
</div>
