@extends('layouts.admin')

@section('title', 'Sản phẩm')

@section('content')
    @include('admin.partials.page-header', [
        'title' => 'Sản phẩm',
        'subtitle' => number_format($counts['all'], 0, ',', '.').' sản phẩm'.($counts['low'] ? ' · '.$counts['low'].' sắp hết hàng' : ''),
        'actionUrl' => route('admin.products.create'),
        'actionLabel' => 'Thêm sản phẩm',
    ])

    @php
        $tabs = [
            'all' => ['Tất cả', $counts['all'], []],
            'active' => ['Đang bán', $counts['active'], ['status' => 'active']],
            'archived' => ['Không bán', $counts['archived'], ['status' => 'archived']],
            'low' => ['Sắp hết hàng', $counts['low'], ['show' => 'low']],
        ];
        $kept = array_filter(['q' => $filters['q'], 'category' => $filters['category'], 'brand' => $filters['brand'], 'per_page' => request('per_page')]);
        $isShow = in_array($tab, ['low', 'out', 'no_image'], true);
        $isStatus = in_array($tab, ['active', 'archived'], true);
        $hasFilter = $filters['q'] !== '' || $filters['category'] || $filters['brand'];
    @endphp

    <nav class="admin-tabs" aria-label="Lọc theo trạng thái">
        @foreach ($tabs as $key => [$label, $count, $params])
            <a href="{{ route('admin.products.index', $kept + $params) }}" class="admin-tab" @if ($tab === $key) aria-current="page" @endif>{{ $label }} <small>{{ number_format($count, 0, ',', '.') }}</small></a>
        @endforeach
        @if (in_array($tab, ['out', 'no_image'], true))
            <span class="admin-tab" aria-current="page">{{ $tab === 'out' ? 'Hết hàng nhưng đang bán' : 'Chưa có ảnh' }}</span>
        @endif
    </nav>

    <form method="GET" action="{{ route('admin.products.index') }}" class="admin-toolbar" role="search">
        @if ($isShow)
            <input type="hidden" name="show" value="{{ $tab }}">
        @elseif ($isStatus)
            <input type="hidden" name="status" value="{{ $tab }}">
        @endif
        <label class="admin-search">
            <span class="sr-only">Tìm sản phẩm</span>
            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
            <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="Tìm theo tên hoặc SKU" class="field">
        </label>
        <select name="category" aria-label="Danh mục" onchange="this.form.requestSubmit()">
            <option value="">Tất cả danh mục</option>
            @foreach ($categories as $category)
                <option value="{{ $category->id }}" @selected($filters['category'] === $category->id)>{{ $category->name }}</option>
            @endforeach
        </select>
        <select name="brand" aria-label="Thương hiệu" onchange="this.form.requestSubmit()">
            <option value="">Tất cả thương hiệu</option>
            @foreach ($brands as $brand)
                <option value="{{ $brand->id }}" @selected($filters['brand'] === $brand->id)>{{ $brand->name }}</option>
            @endforeach
        </select>
        <button type="submit" class="admin-action admin-action-secondary">Tìm</button>
        @if ($hasFilter)
            <a href="{{ route('admin.products.index', array_filter(['status' => $isStatus ? $tab : null, 'show' => $isShow ? $tab : null])) }}" class="admin-action admin-action-quiet">Xóa lọc</a>
        @endif
    </form>

    <x-excel-tools resource="products" />

    <x-admin-table :paginator="$products" :sorting="$sorting" :searchable="false"
        :sortable="['Tên sản phẩm' => 'name', 'Danh mục' => 'category', 'Thương hiệu' => 'brand', 'Tồn kho' => 'stock', 'Trạng thái' => 'status']"
        :header="['Tên sản phẩm', 'Danh mục', 'Thương hiệu', 'Tồn kho', 'Trạng thái', 'Thao tác']">
        @forelse ($products as $product)
            @php
                $stock = (int) $product->stock_total;
                $stockState = $product->variants_count > 0 && $stock === 0 ? 'out' : ($product->low_variants_count > 0 ? 'low' : 'ok');
                $thumbnail = $product->images->firstWhere('is_primary', true) ?? $product->images->first();
            @endphp
            <tr>
                <td class="px-4 py-3">
                    <a href="{{ route('admin.products.show', $product) }}" class="flex min-w-56 items-center gap-3 font-medium text-gray-900">
                        <span class="flex h-12 w-10 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-gray-100 text-gray-500">
                            @if ($thumbnail)<img src="{{ \Illuminate\Support\Facades\Storage::disk(config('filesystems.image_disk'))->url($thumbnail->path) }}" alt="" class="size-full object-cover" loading="lazy">@else<x-icon name="image" />@endif
                        </span>
                        <span class="max-w-72"><span class="block leading-5">{{ $product->name }}</span><span class="mt-1 block text-xs font-normal tabular-nums text-gray-600">{{ number_format((float) $product->base_price, 0, ',', '.') }} ₫ · {{ $product->variants_count }} biến thể</span></span>
                    </a>
                </td>
                <td class="px-4 py-3 text-gray-600">{{ $product->category?->name ?? '—' }}</td>
                <td class="px-4 py-3 text-gray-600">{{ $product->brand?->name ?? '—' }}</td>
                <td class="px-4 py-3">
                    @if ($product->variants_count === 0)
                        <span class="text-xs text-gray-600">Chưa có biến thể</span>
                    @else
                        <span @class(['admin-stock', 'admin-stock-low' => $stockState === 'low', 'admin-stock-out' => $stockState === 'out'])><i style="--fill: {{ min(100, $stock) }}%" aria-hidden="true"></i>{{ $stockState === 'out' ? 'Hết hàng' : number_format($stock, 0, ',', '.').($stockState === 'low' ? ' · sắp hết' : '') }}</span>
                    @endif
                </td>
                <td class="px-4 py-3">
                    <x-admin.product-status :value="$product->status" />
                </td>
                <td class="px-4 py-3 text-right"><div class="admin-row-actions">
                    <x-featured-toggle :product="$product" />
                    <a href="{{ route('admin.products.edit', $product) }}" class="admin-row-action"><x-icon name="edit" class="size-3.5" /> Sửa</a>
                    <div class="admin-menu" x-data="{ open: false, top: 0, right: 0, toggle() { const r = this.$refs.trigger.getBoundingClientRect(); this.top = r.bottom + 4; this.right = window.innerWidth - r.right; this.open = !this.open; } }" x-on:click.outside="open = false" x-on:keydown.escape="open = false" x-on:scroll.window="open = false">
                        <button type="button" class="admin-menu-trigger" x-ref="trigger" x-on:click="toggle()" :aria-expanded="open" aria-haspopup="menu" aria-label="Thao tác khác: {{ $product->name }}"><i class="fa-solid fa-ellipsis-vertical" aria-hidden="true"></i></button>
                        <div class="admin-menu-list" x-show="open" x-cloak role="menu" :style="`position:fixed;top:${top}px;right:${right}px;left:auto;margin:0`">
                            <a href="{{ route('admin.products.show', $product) }}" class="admin-menu-item" role="menuitem"><x-icon name="eye" class="size-3.5" /> Xem chi tiết</a>
                            <form method="POST" action="{{ route('admin.products.destroy', $product) }}"
                                  x-on:submit.prevent="open = false; $dispatch('admin-confirm', { form: $el, message: 'Xóa sản phẩm này?' })">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="admin-menu-item is-danger" role="menuitem"><x-icon name="delete" class="size-3.5" /> Xóa sản phẩm</button>
                            </form>
                        </div>
                    </div>
                </div></td>
            </tr>
        @empty
            <tr>
                <td colspan="6" class="px-4 py-8 text-center text-gray-600">{{ $hasFilter || $tab !== 'all' ? 'Không có sản phẩm nào khớp bộ lọc.' : 'Chưa có sản phẩm nào.' }}</td>
            </tr>
        @endforelse
    </x-admin-table>
@endsection
