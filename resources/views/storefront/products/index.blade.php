@extends('layouts.app')
@section('title', 'Sản phẩm — ' . config('app.name', 'Fashion Store'))
@section('content')
    @php
        $sortQuery = $sort !== 'moi-nhat' ? ['sort' => $sort] : [];
        $brandQuery = $activeBrand ? ['brand' => $activeBrand] : [];

        $matchesBranch = function ($category) use ($activeCategory) {
            if ($category->slug === $activeCategory) {
                return true;
            }

            return $category->children->contains(fn ($child) => $child->slug === $activeCategory
                || $child->children->contains('slug', $activeCategory));
        };
        $activeL1 = $categories->first($matchesBranch);
        $activeL2 = $activeL1?->children->first(fn ($c) => $c->slug === $activeCategory
            || $c->children->contains('slug', $activeCategory));
    @endphp

    <section aria-labelledby="products-title"
             x-data="{ density: ['3', '4', '5'].includes(localStorage.getItem('catalogDensityCompact')) ? localStorage.getItem('catalogDensityCompact') : '5' }"
             x-init="$watch('density', value => localStorage.setItem('catalogDensityCompact', value))">
        <div class="mb-5">
            <p class="eyebrow">Toàn bộ bộ sưu tập</p>
            <h1 id="products-title" class="display-title mt-3 text-3xl sm:text-4xl">Sản phẩm</h1>
        </div>

        <div class="catalog-bar">
            <div class="catalog-bar-row">
                <span class="catalog-tag">[ Tất cả sản phẩm ]</span>
                <p class="catalog-count">[ {{ $products->total() }} sản phẩm ]</p>
                <div class="flex flex-wrap items-center gap-4">
                    <form method="GET" action="{{ route('products.index') }}" class="flex items-center gap-2">
                        @if ($activeBrand)
                            <input type="hidden" name="brand" value="{{ $activeBrand }}">
                        @endif
                        @if ($activeCategory)
                            <input type="hidden" name="category" value="{{ $activeCategory }}">
                        @endif
                        <label for="sort" class="text-xs text-gray-400">Sắp xếp</label>
                        <select id="sort" name="sort" onchange="this.form.submit()" class="catalog-select">
                            <option value="moi-nhat" @selected($sort === 'moi-nhat')>Mới nhất</option>
                            <option value="gia-tang" @selected($sort === 'gia-tang')>Giá tăng dần</option>
                            <option value="gia-giam" @selected($sort === 'gia-giam')>Giá giảm dần</option>
                        </select>
                    </form>
                    <div class="hidden items-center gap-1 lg:flex" role="group" aria-label="Kích thước thẻ sản phẩm">
                        <span class="mr-1 text-[11px] text-gray-400">Kích thước</span>
                        <button type="button" class="density-btn" :aria-pressed="density === '3'" x-on:click="density = '3'" aria-label="Thẻ lớn, 3 cột" title="Thẻ lớn">3</button>
                        <button type="button" class="density-btn" :aria-pressed="density === '4'" x-on:click="density = '4'" aria-label="Thẻ vừa, 4 cột" title="Thẻ vừa">4</button>
                        <button type="button" class="density-btn" :aria-pressed="density === '5'" x-on:click="density = '5'" aria-label="Thẻ nhỏ, 5 cột" title="Thẻ nhỏ">5</button>
                    </div>
                </div>
            </div>

            <div class="catalog-filter-row">
                <span class="catalog-filter-label">Danh mục</span>
                <a href="{{ route('products.index', [...$sortQuery, ...$brandQuery]) }}" class="catalog-chip @if (! $activeCategory) is-active @endif">Tất cả</a>
                @foreach ($categories as $category)
                    <a href="{{ route('products.index', [...$sortQuery, ...$brandQuery, 'category' => $category->slug]) }}"
                       class="catalog-chip @if ($activeL1?->id === $category->id) is-active @endif">{{ $category->name }}</a>
                @endforeach
            </div>

            @if ($activeL1 && $activeL1->children->isNotEmpty())
                <div class="catalog-filter-row catalog-filter-row-nested">
                    <span class="catalog-filter-label">&mdash;</span>
                    <a href="{{ route('products.index', [...$sortQuery, ...$brandQuery, 'category' => $activeL1->slug]) }}"
                       class="catalog-chip @if ($activeCategory === $activeL1->slug) is-active @endif">Tất cả {{ \Illuminate\Support\Str::lower($activeL1->name) }}</a>
                    @foreach ($activeL1->children as $child)
                        <a href="{{ route('products.index', [...$sortQuery, ...$brandQuery, 'category' => $child->slug]) }}"
                           class="catalog-chip @if ($activeL2?->id === $child->id) is-active @endif">{{ $child->name }}</a>
                    @endforeach
                </div>
            @endif

            @if ($activeL2 && $activeL2->children->isNotEmpty())
                <div class="catalog-filter-row catalog-filter-row-nested">
                    <span class="catalog-filter-label">&mdash;&mdash;</span>
                    <a href="{{ route('products.index', [...$sortQuery, ...$brandQuery, 'category' => $activeL2->slug]) }}"
                       class="catalog-chip @if ($activeCategory === $activeL2->slug) is-active @endif">Tất cả {{ \Illuminate\Support\Str::lower($activeL2->name) }}</a>
                    @foreach ($activeL2->children as $style)
                        <a href="{{ route('products.index', [...$sortQuery, ...$brandQuery, 'category' => $style->slug]) }}"
                           class="catalog-chip @if ($activeCategory === $style->slug) is-active @endif">{{ $style->name }}</a>
                    @endforeach
                </div>
            @endif

            <div class="catalog-filter-row">
                <span class="catalog-filter-label">Thương hiệu</span>
                @php($categoryQuery = $activeCategory ? ['category' => $activeCategory] : [])
                <a href="{{ route('products.index', [...$sortQuery, ...$categoryQuery]) }}" class="catalog-chip @if (! $activeBrand) is-active @endif">Tất cả</a>
                @foreach ($brands as $brand)
                    <a href="{{ route('products.index', [...$sortQuery, ...$categoryQuery, 'brand' => $brand->slug]) }}"
                       class="catalog-chip @if ($activeBrand === $brand->slug) is-active @endif">{{ $brand->name }}</a>
                @endforeach
            </div>
        </div>

        @if ($products->isEmpty())
            <div class="flex flex-col items-center gap-3 rounded-2xl border border-dashed border-gray-300 bg-white px-6 py-16 text-center">
                <x-icon name="box" class="size-8 text-gray-300" />
                <p class="text-sm text-gray-500">Không có sản phẩm nào phù hợp.</p>
            </div>
        @else
            <div :class="{
                    'grid grid-cols-2 gap-x-4 gap-y-7 sm:grid-cols-3': density === '3',
                    'grid grid-cols-2 gap-x-4 gap-y-7 sm:grid-cols-3 lg:grid-cols-4': density === '4',
                    'grid grid-cols-2 gap-x-3 gap-y-6 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5': density === '5',
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
                            <p class="pg-brand">{{ $product->brand?->name ?? 'Fashion Store' }}</p>
                            <h3>{{ $product->name }}</h3>
                            <span class="pg-price">{{ number_format((float) $product->base_price, 0) }} ₫</span>
                        </div>
                    </a>
                @endforeach
            </div>

            <div class="mt-8">{{ $products->links() }}</div>
        @endif
    </section>
@endsection
