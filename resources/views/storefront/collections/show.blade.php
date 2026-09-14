@extends('layouts.app')
@section('title', 'Bộ sưu tập ' . $brand->name . ' — ' . config('app.name', 'Fashion Store'))
@section('content')
    <nav aria-label="Breadcrumb" class="mb-6 flex flex-wrap items-center gap-1.5 text-xs text-gray-500">
        <a href="{{ route('home') }}" class="hover:text-brand">Trang chủ</a>
        <x-icon name="arrow" class="size-2.5" />
        <a href="{{ route('collections.index') }}" class="hover:text-brand">Bộ sưu tập</a>
        <x-icon name="arrow" class="size-2.5" />
        <span class="text-gray-900">{{ $brand->name }}</span>
    </nav>

    <section class="mb-9 overflow-hidden rounded-3xl bg-brand-soft/60 px-7 py-12 sm:px-12" aria-labelledby="brand-title">
        <p class="eyebrow">Bộ sưu tập thương hiệu</p>
        <h1 id="brand-title" class="display-title mt-3 text-4xl sm:text-5xl">{{ $brand->name }}</h1>
        @if ($brand->description)
            <p class="mt-4 max-w-xl text-sm leading-7 text-gray-600">{{ $brand->description }}</p>
        @endif
        <p class="mt-5 text-xs font-medium text-gray-500">{{ $products->total() }} sản phẩm thuộc {{ $categories->count() }} loại</p>
    </section>

    <section aria-labelledby="products-title" x-data="{ density: localStorage.getItem('catalogDensity') || '3' }"
             x-init="$watch('density', v => localStorage.setItem('catalogDensity', v))">
        @if ($categories->isNotEmpty())
            <div class="catalog-bar">
                <div class="catalog-filter-row">
                    <span class="catalog-filter-label">Loại</span>
                    <a href="{{ route('collections.show', $brand) }}" class="catalog-chip @if (! $activeCategory) is-active @endif">Tất cả</a>
                    @foreach ($categories as $category)
                        <a href="{{ route('collections.show', [$brand, 'category' => $category->slug]) }}"
                           class="catalog-chip @if ($activeCategory === $category->slug) is-active @endif">{{ $category->name }}</a>
                    @endforeach
                    <div class="ml-auto flex items-center gap-1 pl-3" role="group" aria-label="Số cột hiển thị">
                        <button type="button" class="density-btn" :aria-pressed="density === '2'" x-on:click="density = '2'" aria-label="Lưới 2 cột">2</button>
                        <button type="button" class="density-btn" :aria-pressed="density === '3'" x-on:click="density = '3'" aria-label="Lưới 3 cột">3</button>
                        <button type="button" class="density-btn" :aria-pressed="density === '4'" x-on:click="density = '4'" aria-label="Lưới 4 cột">4</button>
                    </div>
                </div>
            </div>
        @endif

        @if ($products->isEmpty())
            <div class="flex flex-col items-center gap-3 rounded-2xl border border-dashed border-gray-300 bg-white px-6 py-16 text-center">
                <x-icon name="box" class="size-8 text-gray-300" />
                <p class="text-sm text-gray-500">Không có sản phẩm nào phù hợp.</p>
            </div>
        @else
            <div :class="{
                    'grid grid-cols-2 gap-x-5 gap-y-9 sm:grid-cols-2': density === '2',
                    'grid grid-cols-2 gap-x-5 gap-y-9 sm:grid-cols-3': density === '3',
                    'grid grid-cols-2 gap-x-5 gap-y-9 sm:grid-cols-3 lg:grid-cols-4': density === '4',
                 }">
                @foreach ($products as $product)
                    @php($image = $product->images->first())
                    @php($outOfStock = (int) $product->stock_total === 0)
                    <a href="{{ route('products.show', $product) }}" class="pg-card group">
                        <div class="pg-media">
                            <div class="pg-tags">
                                @if ($product->is_featured)
                                    <span class="product-card-badge">Nổi bật</span>
                                @endif
                                @if ($outOfStock)
                                    <span class="product-card-badge product-card-badge-muted">Hết hàng</span>
                                @endif
                            </div>
                            @if ($image)
                                <img src="{{ \Illuminate\Support\Facades\Storage::disk('s3')->url($image->path) }}"
                                     alt="{{ $image->alt_text ?? $product->name }}" loading="lazy">
                            @else
                                <x-icon name="shirt" class="size-12" />
                            @endif
                        </div>
                        <div class="pg-body">
                            <div class="min-w-0">
                                <p class="pg-brand">{{ $product->category?->name }}</p>
                                <h3>{{ $product->name }}</h3>
                            </div>
                            <span class="pg-price">{{ number_format((float) $product->base_price, 0) }} ₫</span>
                        </div>
                    </a>
                @endforeach
            </div>

            <div class="mt-8">{{ $products->links() }}</div>
        @endif
    </section>
@endsection
