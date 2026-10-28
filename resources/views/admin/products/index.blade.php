@extends('layouts.admin')

@section('title', 'Sản phẩm')

@section('content')
    @include('admin.partials.page-header', [
        'title' => 'Sản phẩm',
        'actionUrl' => route('admin.products.create'),
        'actionLabel' => 'Thêm sản phẩm',
    ])

    <x-excel-tools resource="products" />

    @php
        $filtering = array_filter($filters) !== [];
        // The sort and page size survive a filter change; only plain values, never arrays from a hand-edited URL.
        $kept = array_filter(request()->only(['sort', 'direction', 'per_page']), fn ($value) => is_string($value) && $value !== '');
    @endphp
    {{-- Changing a select applies it at once; the button is there for keyboards and no-JS. --}}
    <form method="GET" action="{{ route('admin.products.index') }}" class="mb-4 flex flex-wrap items-center gap-2" aria-label="Lọc sản phẩm">
        @foreach ($kept as $name => $value)
            <input type="hidden" name="{{ $name }}" value="{{ $value }}">
        @endforeach
        <label for="product-brand" class="sr-only">Thương hiệu</label>
        <select id="product-brand" name="brand" class="field min-h-10 sm:w-auto" onchange="this.form.requestSubmit()">
            <option value="">Mọi thương hiệu</option>
            @foreach ($brands as $brand)
                <option value="{{ $brand->id }}" @selected($filters['brand'] === $brand->id)>{{ $brand->name }}</option>
            @endforeach
        </select>
        <label for="product-category" class="sr-only">Danh mục</label>
        <select id="product-category" name="category" class="field min-h-10 sm:w-auto" onchange="this.form.requestSubmit()">
            <option value="">Mọi danh mục</option>
            @foreach ($categories as $category)
                <option value="{{ $category['id'] }}" @selected($filters['category'] === $category['id'])>{{ str_repeat('— ', $category['depth']) }}{{ $category['name'] }}</option>
            @endforeach
        </select>
        <x-admin.action type="submit" variant="secondary" icon="search">Lọc</x-admin.action>
        @if ($filtering)
            <span class="text-sm text-gray-500" role="status">{{ $products->total() }} sản phẩm khớp</span>
            <a href="{{ route('admin.products.index', $kept) }}" class="px-2 text-sm font-medium text-gray-500 hover:text-gray-900 hover:underline">Xoá lọc</a>
        @endif
    </form>

    <x-admin-table :paginator="$products" :sorting="$sorting"
        :sortable="['Tên sản phẩm' => 'name', 'Danh mục' => 'category', 'Thương hiệu' => 'brand', 'Biến thể' => 'variants_count', 'Trạng thái' => 'status', 'Nổi bật' => 'is_featured']"
        :header="['Tên sản phẩm', 'Danh mục', 'Thương hiệu', 'Biến thể', 'Trạng thái', 'Nổi bật', 'Thao tác']">
        @forelse ($products as $product)
            <tr>
                <td class="px-4 py-3">
                    @php($thumbnail = $product->images->firstWhere('is_primary', true) ?? $product->images->first())
                    <a href="{{ route('admin.products.show', $product) }}" class="flex min-w-56 items-center gap-3 font-medium text-gray-900">
                        <span class="flex h-12 w-10 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-gray-100 text-gray-400">
                            @if ($thumbnail)<img src="{{ \Illuminate\Support\Facades\Storage::disk(config('filesystems.image_disk'))->url($thumbnail->path) }}" alt="" class="size-full object-cover" loading="lazy">@else<x-icon name="image" />@endif
                        </span>
                        <span class="max-w-64"><span class="block leading-5">{{ $product->name }}</span><span class="mt-1 block text-xs font-normal tabular-nums text-gray-500">{{ number_format((float) $product->base_price, 0, ',', '.') }} ₫</span></span>
                    </a>
                </td>
                <td class="px-4 py-3 text-gray-600">{{ $product->category?->name ?? '—' }}</td>
                <td class="px-4 py-3 text-gray-600">{{ $product->brand?->name ?? '—' }}</td>
                <td class="px-4 py-3 text-gray-600">{{ $product->variants_count }}</td>
                <td class="px-4 py-3">
                    <x-admin.product-status :value="$product->status" />
                </td>
                <td class="px-4 py-3"><x-featured-toggle :product="$product" /></td>
                <td class="px-4 py-3 text-right"><div class="admin-row-actions">
                    <a href="{{ route('admin.products.edit', $product) }}" class="admin-row-action"><x-icon name="edit" class="size-3.5" /> Sửa</a>
                    <form method="POST" action="{{ route('admin.products.destroy', $product) }}" class="inline"
                          x-on:submit.prevent="$dispatch('admin-confirm', { form: $el, message: 'Xóa sản phẩm này?' })">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="admin-row-action"><x-icon name="delete" class="size-3.5" /> Xóa</button>
                    </form>
                </div></td>
            </tr>
        @empty
            <tr>
                <td colspan="7" class="px-4 py-8 text-center text-gray-500">{{ $filtering ? 'Không có sản phẩm nào khớp bộ lọc.' : 'Chưa có sản phẩm nào.' }}</td>
            </tr>
        @endforelse
    </x-admin-table>
@endsection
