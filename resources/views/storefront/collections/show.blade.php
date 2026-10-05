@extends('layouts.app')
@section('title', 'Bộ sưu tập ' . $brand->name . ' — ' . config('app.name', 'Fashion Store'))
@section('content')
    <nav aria-label="Breadcrumb" class="mb-5 flex flex-wrap items-center gap-2 text-[13px] text-gray-600">
        <a href="{{ route('home') }}" class="py-1.5 hover:text-brand">Trang chủ</a>
        <x-icon name="chevron-right" class="size-2.5 text-gray-400" />
        <a href="{{ route('collections.index') }}" class="py-1.5 hover:text-brand">Bộ sưu tập</a>
        <x-icon name="chevron-right" class="size-2.5 text-gray-400" />
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

    <section aria-labelledby="products-title">
        @if ($categories->isNotEmpty())
            <div class="catalog-bar">
                <div class="catalog-bar-row">
                    <div class="catalog-chips" role="group" aria-label="Loại sản phẩm">
                        <a href="{{ route('collections.show', $brand) }}" class="catalog-chip @if (! $activeCategory) is-active @endif">Tất cả</a>
                        @foreach ($categories as $category)
                            <a href="{{ route('collections.show', [$brand, 'category' => $category->slug]) }}"
                               class="catalog-chip @if ($activeCategory === $category->slug) is-active @endif">{{ $category->name }}</a>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        <div class="mt-5">
            @if ($products->isEmpty())
                <div class="catalog-empty">
                    <span class="catalog-empty-icon"><x-icon name="box" /></span>
                    <h2 class="mt-4 mb-1 text-lg font-semibold">Chưa có sản phẩm</h2>
                    <p class="mx-auto max-w-sm text-sm text-gray-600">Không có sản phẩm nào phù hợp trong bộ sưu tập này.</p>
                </div>
            @else
                <div class="catalog-grid">
                    @foreach ($products as $product)
                        <x-storefront.product-card :product="$product" :eyebrow="$product->category?->name" />
                    @endforeach
                </div>

                @if ($products->hasPages())
                    <div class="catalog-pager">
                        <p class="text-sm text-gray-600">Hiển thị {{ number_format($products->firstItem(), 0, ',', '.') }}–{{ number_format($products->lastItem(), 0, ',', '.') }} trong {{ number_format($products->total(), 0, ',', '.') }} sản phẩm</p>
                        {{ $products->onEachSide(1)->links('components.pagination-links') }}
                    </div>
                @endif
            @endif
        </div>
    </section>
@endsection
