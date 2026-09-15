@extends('layouts.admin')

@section('title', 'Sản phẩm')

@section('content')
    @include('admin.partials.page-header', [
        'title' => 'Sản phẩm',
        'subtitle' => 'Quản lý thông tin, biến thể và trạng thái hiển thị của sản phẩm.',
        'actionUrl' => route('admin.products.create'),
        'actionLabel' => 'Thêm sản phẩm',
    ])

    <x-excel-tools resource="products" />

    <x-admin-table :paginator="$products" :sorting="$sorting"
        :sortable="['Tên sản phẩm' => 'name', 'Danh mục' => 'category', 'Thương hiệu' => 'brand', 'Biến thể' => 'variants_count', 'Trạng thái' => 'status', 'Nổi bật' => 'is_featured']"
        :header="['Tên sản phẩm', 'Danh mục', 'Thương hiệu', 'Biến thể', 'Trạng thái', 'Nổi bật', 'Thao tác']">
        @forelse ($products as $product)
            <tr>
                <td class="px-4 py-3">
                    @php($thumbnail = $product->images->firstWhere('is_primary', true) ?? $product->images->first())
                    <a href="{{ route('admin.products.show', $product) }}" class="flex min-w-56 items-center gap-3 font-medium text-gray-900">
                        <span class="flex h-12 w-10 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-gray-100 text-gray-400">
                            @if ($thumbnail)<img src="{{ \Illuminate\Support\Facades\Storage::disk('s3')->url($thumbnail->path) }}" alt="" class="size-full object-cover" loading="lazy">@else<x-icon name="image" />@endif
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
                <td colspan="7" class="px-4 py-8 text-center text-gray-500">Chưa có sản phẩm nào.</td>
            </tr>
        @endforelse
    </x-admin-table>
@endsection
