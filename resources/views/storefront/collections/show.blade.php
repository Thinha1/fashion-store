@extends('layouts.app')
@section('title', 'Bộ sưu tập ' . $brand->name . ' — ' . config('app.name', 'Fashion Store'))
@section('content')
    <div class="container page" x-data="{ density: ['2', '3', '4'].includes(localStorage.getItem('catalogDensity')) ? localStorage.getItem('catalogDensity') : '4' }"
         x-init="$watch('density', v => localStorage.setItem('catalogDensity', v))">
        <nav aria-label="Breadcrumb" class="crumbs">
            <ol>
                <li><a href="{{ route('home') }}">Trang chủ</a></li>
                <li><a href="{{ route('collections.index') }}">Bộ sưu tập</a></li>
                <li aria-current="page">{{ $brand->name }}</li>
            </ol>
        </nav>

        <header class="brand-head" aria-labelledby="brand-title">
            <h1 id="brand-title">{{ $brand->name }}</h1>
            <div class="brand-head-text">
                @if ($brand->description)
                    <p class="lead">{{ $brand->description }}</p>
                @endif
                <p class="caption">{{ $products->total() }} sản phẩm thuộc {{ $categories->count() }} loại</p>
            </div>
        </header>

        @if ($categories->isNotEmpty())
            <div class="catalog-bar">
                <nav class="catalog-tabs" aria-label="Loại sản phẩm">
                    <a href="{{ route('collections.show', $brand) }}" class="catalog-tab" @if (! $activeCategory) aria-current="page" @endif>Tất cả</a>
                    @foreach ($categories as $category)
                        <a href="{{ route('collections.show', [$brand, 'category' => $category->slug]) }}"
                           class="catalog-tab" @if ($activeCategory === $category->slug) aria-current="page" @endif>{{ $category->name }}</a>
                    @endforeach
                </nav>
                <div class="catalog-tools">
                    <fieldset class="catalog-density" x-cloak>
                        <legend class="visually-hidden">Số cột hiển thị</legend>
                        <button type="button" class="density-btn" :aria-pressed="(density === '2').toString()" x-on:click="density = '2'" aria-label="Lưới 2 cột">2</button>
                        <button type="button" class="density-btn" :aria-pressed="(density === '3').toString()" x-on:click="density = '3'" aria-label="Lưới 3 cột">3</button>
                        <button type="button" class="density-btn" :aria-pressed="(density === '4').toString()" x-on:click="density = '4'" aria-label="Lưới 4 cột">4</button>
                    </fieldset>
                </div>
            </div>
        @endif

        <section aria-labelledby="brand-title">
            @if ($products->isEmpty())
                <div class="catalog-empty">
                    <h2>Không có sản phẩm nào phù hợp.</h2>
                    <p>Chọn loại khác, hoặc xem tất cả sản phẩm của {{ $brand->name }}.</p>
                    <a href="{{ route('collections.show', $brand) }}" class="btn btn-primary">Xem tất cả</a>
                </div>
            @else
                <ul class="pgrid catalog-grid" role="list" data-density="4" :data-density="density">
                    @foreach ($products as $product)
                        <li>
                            <x-storefront.product-card :product="$product" :kicker="$product->category?->name ?? $brand->name"
                                :badge="(int) $product->stock_total === 0 ? 'Hết hàng' : ($product->is_featured ? 'Nổi bật' : null)" />
                        </li>
                    @endforeach
                </ul>

                <div class="catalog-pages">{{ $products->links('components.pagination-links') }}</div>
            @endif
        </section>
    </div>
@endsection
