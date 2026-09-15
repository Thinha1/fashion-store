@extends('layouts.admin')

@section('title', $product->name)

@section('content')
    @include('admin.partials.page-header', [
        'title' => $product->name,
        'subtitle' => 'Chi tiết sản phẩm và thông tin liên quan.',
        'actionUrl' => route('admin.products.edit', $product),
        'actionLabel' => 'Chỉnh sửa',
        'actionIcon' => 'edit',
    ])

    <div @class(['admin-detail-grid' => $product->images->isNotEmpty()])>
    <div class="min-w-0 space-y-6">
    <x-admin.panel title="Thông tin sản phẩm" icon="shirt"><dl class="admin-facts sm:grid-cols-3">
        <div>
            <dt class="text-xs text-gray-500">Danh mục</dt>
            <dd class="mt-1 text-sm text-gray-900">{{ $product->category?->name ?? '—' }}</dd>
        </div>
        <div>
            <dt class="text-xs text-gray-500">Thương hiệu</dt>
            <dd class="mt-1 text-sm text-gray-900">{{ $product->brand?->name ?? '—' }}</dd>
        </div>
        <div>
            <dt class="text-xs text-gray-500">Giá cơ bản</dt>
            <dd class="mt-1 text-sm text-gray-900">{{ number_format((float) $product->base_price, 0) }} ₫</dd>
        </div>
        <div>
            <dt class="text-xs text-gray-500">Trạng thái</dt>
            <dd class="mt-1 text-sm text-gray-900"><x-admin.status :value="$product->status" /></dd>
        </div>
        <div>
            <dt class="text-xs text-gray-500">Sản phẩm nổi bật</dt>
            <dd class="mt-1 text-sm text-gray-900">{{ $product->is_featured ? 'Có' : 'Không' }}</dd>
        </div>
    </dl></x-admin.panel>

    @if ($product->description)
        <x-admin.panel title="Mô tả">
            <p class="whitespace-pre-line text-sm leading-6 text-gray-700">{{ $product->description }}</p>
        </x-admin.panel>
    @endif
    </div>

    @if ($product->images->count())
        <x-admin.panel title="Ảnh sản phẩm" icon="image" class="self-start">
            <div class="grid grid-cols-2 gap-3">
                @foreach ($product->images as $image)
                    <figure class="overflow-hidden rounded-lg border border-gray-200">
                        <img src="{{ \Illuminate\Support\Facades\Storage::disk('s3')->url($image->path) }}" alt="{{ $image->alt_text ?: $product->name }}" class="aspect-[4/5] w-full object-cover">
                        <figcaption class="px-2 py-1 text-xs text-gray-500">
                            @if ($image->is_primary) <span class="text-green-600">Ảnh chính</span> @endif
                        </figcaption>
                    </figure>
                @endforeach
            </div>
        </x-admin.panel>
    @endif
    </div>

    @if ($product->variants->count())
        <div class="mt-6">
            <h2 class="text-sm font-semibold text-gray-700">Biến thể ({{ $product->variants->count() }})</h2>
            <x-admin-table :header="['Size', 'Màu', 'SKU', 'Giá', 'Tồn kho', 'Trạng thái']" class="mt-3">
                @foreach ($product->variants as $variant)
                    <tr>
                        <td class="px-4 py-3">{{ $variant->size }}</td>
                        <td class="px-4 py-3">{{ $variant->color }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $variant->sku }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ number_format((float) ($variant->price ?? $product->base_price), 0, ',', '.') }} ₫</td>
                        <td class="px-4 py-3 text-gray-600">{{ $variant->stock_quantity }}</td>
                        <td class="px-4 py-3"><x-admin.status :value="$variant->is_active" /></td>
                    </tr>
                @endforeach
            </x-admin-table>
        </div>
    @endif

    <div class="mt-8">
        <a href="{{ route('admin.products.index') }}" class="text-sm text-gray-600 hover:underline">&larr; Danh sách sản phẩm</a>
    </div>
@endsection
