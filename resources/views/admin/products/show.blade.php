@extends('layouts.admin')

@section('title', $product->name)

@section('content')
    @include('admin.partials.page-header', [
        'title' => $product->name,
        'subtitle' => 'ID '.$product->id.' • slug /'.$product->slug,
        'actions' => '<a href="'.route('admin.products.edit', $product).'" class="text-sm text-gray-700 hover:underline">Sửa</a>',
    ])

    <dl class="grid max-w-3xl grid-cols-1 gap-4 sm:grid-cols-3">
        <div>
            <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Danh mục</dt>
            <dd class="mt-1 text-sm text-gray-900">{{ $product->category?->name ?? '—' }}</dd>
        </div>
        <div>
            <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Thương hiệu</dt>
            <dd class="mt-1 text-sm text-gray-900">{{ $product->brand?->name ?? '—' }}</dd>
        </div>
        <div>
            <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Giá cơ bản</dt>
            <dd class="mt-1 text-sm text-gray-900">{{ number_format((float) $product->base_price, 0) }} ₫</dd>
        </div>
        <div>
            <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Trạng thái</dt>
            <dd class="mt-1 text-sm text-gray-900">{{ $product->status }}</dd>
        </div>
        <div>
            <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">F nổi bật</dt>
            <dd class="mt-1 text-sm text-gray-900">{{ $product->is_featured ? '★' : '—' }}</dd>
        </div>
    </dl>

    @if ($product->description)
        <div class="mt-6 max-w-3xl">
            <h2 class="text-sm font-semibold text-gray-700">Mô tả</h2>
            <p class="mt-2 whitespace-pre-line text-sm text-gray-700">{{ $product->description }}</p>
        </div>
    @endif

    @if ($product->images->count())
        <div class="mt-8">
            <h2 class="text-sm font-semibold text-gray-700">Ảnh</h2>
            <div class="mt-3 grid grid-cols-3 gap-4 sm:grid-cols-6">
                @foreach ($product->images as $image)
                    <figure class="overflow-hidden rounded-md border border-gray-200">
                        <img src="{{ \Illuminate\Support\Facades\Storage::disk('s3')->url($image->path) }}" alt="{{ $image->alt_text }}" class="aspect-square w-full object-cover">
                        <figcaption class="px-2 py-1 text-xs text-gray-500">
                            @if ($image->is_primary) <span class="text-green-600">Ảnh chính</span> @endif
                        </figcaption>
                    </figure>
                @endforeach
            </div>
        </div>
    @endif

    @if ($product->variants->count())
        <div class="mt-8 max-w-3xl">
            <h2 class="text-sm font-semibold text-gray-700">Biến thể ({{ $product->variants->count() }})</h2>
            <x-admin-table :header="['Size', 'Màu', 'SKU', 'Giá', 'Tồn kho', 'Trạng thái']" class="mt-3">
                @foreach ($product->variants as $variant)
                    <tr>
                        <td class="px-4 py-3">{{ $variant->size }}</td>
                        <td class="px-4 py-3">{{ $variant->color }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $variant->sku }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $variant->price ? number_format((float) $variant->price, 0) : '—' }} ₫</td>
                        <td class="px-4 py-3 text-gray-600">{{ $variant->stock_quantity }}</td>
                        <td class="px-4 py-3">{{ $variant->is_active ? 'Hoạt động' : 'Tạm dừng' }}</td>
                    </tr>
                @endforeach
            </x-admin-table>
        </div>
    @endif

    <div class="mt-8">
        <a href="{{ route('admin.products.index') }}" class="text-sm text-gray-600 hover:underline">&larr; Danh sách sản phẩm</a>
    </div>
@endsection
