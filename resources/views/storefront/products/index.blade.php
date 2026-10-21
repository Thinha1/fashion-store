@extends('layouts.app')
@section('title', 'Sản phẩm — ' . config('app.name', 'Fashion Store'))
@section('content')
    @php
        $activeCategory = $filters->category;

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
        <div class="mb-5 flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="eyebrow">Toàn bộ bộ sưu tập</p>
                <h1 id="products-title" class="display-title mt-3 text-3xl sm:text-4xl">
                    @if ($filters->q !== '')
                        Kết quả cho “{{ $filters->q }}”
                    @else
                        Sản phẩm
                    @endif
                </h1>
            </div>
            <form method="GET" action="{{ route('products.index') }}" role="search" class="catalog-search"
                  x-data x-init="if (location.hash === '#tim-kiem') $refs.q.focus()"
                  x-on:hashchange.window="if (location.hash === '#tim-kiem') $refs.q.focus()">
                @include('storefront.products._filter-inputs', ['query' => \Illuminate\Support\Arr::except($filters->query(), ['q', 'sort'])])
                <label for="tim-kiem" class="sr-only">Tìm sản phẩm</label>
                <x-icon name="search" class="catalog-search-icon" />
                <input id="tim-kiem" x-ref="q" type="search" name="q" value="{{ $filters->q }}" maxlength="100"
                       placeholder="Tìm áo, quần, thương hiệu…" autocomplete="off" class="field catalog-search-input">
                <button type="submit" class="btn btn-primary">Tìm</button>
            </form>
        </div>

        <div class="catalog-bar">
            <div class="catalog-bar-row">
                <span class="catalog-tag">[ Tất cả sản phẩm ]</span>
                <p class="catalog-count">[ {{ $products->total() }} sản phẩm ]</p>
                <div class="flex flex-wrap items-center gap-4">
                    <form method="GET" action="{{ route('products.index') }}" class="flex items-center gap-2">
                        @include('storefront.products._filter-inputs', ['query' => \Illuminate\Support\Arr::except($filters->query(), ['sort'])])
                        <label for="sort" class="text-xs text-gray-400">Sắp xếp</label>
                        <select id="sort" name="sort" onchange="this.form.submit()" class="catalog-select">
                            @if ($filters->q !== '')
                                <option value="lien-quan" @selected($filters->sort === 'lien-quan')>Phù hợp nhất</option>
                            @endif
                            <option value="moi-nhat" @selected($filters->sort === 'moi-nhat')>Mới nhất</option>
                            <option value="gia-tang" @selected($filters->sort === 'gia-tang')>Giá tăng dần</option>
                            <option value="gia-giam" @selected($filters->sort === 'gia-giam')>Giá giảm dần</option>
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
                <a href="{{ route('products.index', $filters->query(['category' => null])) }}" class="catalog-chip @if (! $activeCategory) is-active @endif">Tất cả</a>
                @foreach ($categories as $category)
                    <a href="{{ route('products.index', $filters->query(['category' => $category->slug])) }}"
                       class="catalog-chip @if ($activeL1?->id === $category->id) is-active @endif">{{ $category->name }}</a>
                @endforeach
            </div>

            @if ($activeL1 && $activeL1->children->isNotEmpty())
                <div class="catalog-filter-row catalog-filter-row-nested">
                    <span class="catalog-filter-label">&mdash;</span>
                    <a href="{{ route('products.index', $filters->query(['category' => $activeL1->slug])) }}"
                       class="catalog-chip @if ($activeCategory === $activeL1->slug) is-active @endif">Tất cả {{ \Illuminate\Support\Str::lower($activeL1->name) }}</a>
                    @foreach ($activeL1->children as $child)
                        <a href="{{ route('products.index', $filters->query(['category' => $child->slug])) }}"
                           class="catalog-chip @if ($activeL2?->id === $child->id) is-active @endif">{{ $child->name }}</a>
                    @endforeach
                </div>
            @endif

            @if ($activeL2 && $activeL2->children->isNotEmpty())
                <div class="catalog-filter-row catalog-filter-row-nested">
                    <span class="catalog-filter-label">&mdash;&mdash;</span>
                    <a href="{{ route('products.index', $filters->query(['category' => $activeL2->slug])) }}"
                       class="catalog-chip @if ($activeCategory === $activeL2->slug) is-active @endif">Tất cả {{ \Illuminate\Support\Str::lower($activeL2->name) }}</a>
                    @foreach ($activeL2->children as $style)
                        <a href="{{ route('products.index', $filters->query(['category' => $style->slug])) }}"
                           class="catalog-chip @if ($activeCategory === $style->slug) is-active @endif">{{ $style->name }}</a>
                    @endforeach
                </div>
            @endif

            @php
                $activeBrandName = $brands->firstWhere('slug', $filters->brand)?->name;
                $activePriceRange = collect(\App\Services\Catalog\CatalogFilters::PRICE_RANGES)->first(fn ($range) => $filters->isPriceRange($range));
                $dropdowns = [
                    'brand' => [
                        'label' => $activeBrandName ? 'Thương hiệu: '.$activeBrandName : 'Thương hiệu',
                        'active' => $filters->brand !== '',
                        'clear' => ['brand' => null],
                        'options' => $brands->map(fn ($brand) => [
                            'label' => $brand->name,
                            'query' => $filters->query(['brand' => $brand->slug]),
                            'active' => $filters->brand === $brand->slug,
                        ]),
                    ],
                    'size' => [
                        'label' => $filters->sizes ? 'Size: '.implode(', ', $filters->sizes) : 'Size',
                        'active' => $filters->sizes !== [],
                        'clear' => ['size' => null],
                        'options' => collect($filterOptions['sizes'])->map(fn ($size) => [
                            'label' => $size,
                            'query' => $filters->toggle('size', $size),
                            'active' => in_array($size, $filters->sizes, true),
                        ]),
                    ],
                    'color' => [
                        'label' => match (count($filters->colors)) {
                            0 => 'Màu',
                            1 => 'Màu: '.$filters->colors[0],
                            default => 'Màu ('.count($filters->colors).')',
                        },
                        'active' => $filters->colors !== [],
                        'clear' => ['color' => null],
                        'options' => collect($filterOptions['colors'])->map(fn ($color) => [
                            'label' => $color,
                            'query' => $filters->toggle('color', $color),
                            'active' => in_array($color, $filters->colors, true),
                            'swatch' => \App\Support\ColorPalette::hex($color),
                        ]),
                    ],
                    'price' => [
                        'label' => $filters->hasPriceFilter() ? 'Giá: '.($activePriceRange['label'] ?? 'tùy chọn') : 'Giá',
                        'active' => $filters->hasPriceFilter(),
                        'clear' => ['price_min' => null, 'price_max' => null],
                        'options' => collect(\App\Services\Catalog\CatalogFilters::PRICE_RANGES)->map(fn ($range) => [
                            'label' => $range['label'],
                            'query' => $filters->query(['price_min' => $range['min'], 'price_max' => $range['max']]),
                            'active' => $filters->isPriceRange($range),
                        ]),
                    ],
                ];
            @endphp
            <div class="catalog-filter-bar" x-data="{ open: null }" x-on:keydown.escape="open = null">
                <span class="catalog-filter-label">Lọc</span>
                @foreach ($dropdowns as $key => $dropdown)
                    @continue($dropdown['options']->isEmpty())
                    <div class="catalog-dropdown" x-on:click.outside="if (open === '{{ $key }}') open = null">
                        <button type="button" class="catalog-dropdown-btn @if ($dropdown['active']) is-active @endif"
                                aria-controls="filter-{{ $key }}" :aria-expanded="open === '{{ $key }}'"
                                x-on:click="open = open === '{{ $key }}' ? null : '{{ $key }}'">
                            {{ $dropdown['label'] }} <x-icon name="chevron-down" class="size-2.5" />
                        </button>
                        <div id="filter-{{ $key }}" x-cloak x-show="open === '{{ $key }}'" class="catalog-dropdown-panel">
                            <a href="{{ route('products.index', $filters->query($dropdown['clear'])) }}"
                               class="catalog-chip @if (! $dropdown['active']) is-active @endif">Tất cả</a>
                            @foreach ($dropdown['options'] as $option)
                                <a href="{{ route('products.index', $option['query']) }}"
                                   class="catalog-chip @if ($option['active']) is-active @endif">@isset($option['swatch'])<span class="catalog-swatch" style="background-color: {{ $option['swatch'] }}" aria-hidden="true"></span>@endisset{{ $option['label'] }}</a>
                            @endforeach
                        </div>
                    </div>
                @endforeach
                <a href="{{ route('products.index', $filters->query(['in_stock' => $filters->inStock ? null : 1])) }}"
                   class="catalog-dropdown-btn @if ($filters->inStock) is-active @endif">
                    @if ($filters->inStock)<x-icon name="check" class="size-2.5" />@endif Còn hàng
                </a>
                @if ($filters->isFiltered())
                    <a href="{{ route('products.index') }}" class="catalog-chip">Xóa bộ lọc</a>
                @endif
            </div>
        </div>

        @if ($products->isEmpty())
            <div class="flex flex-col items-center gap-3 rounded-2xl border border-dashed border-gray-300 bg-white px-6 py-16 text-center">
                <x-icon name="box" class="size-8 text-gray-300" />
                <p class="text-sm text-gray-500">
                    @if ($filters->q !== '')
                        Không tìm thấy sản phẩm nào cho “{{ $filters->q }}”.
                    @else
                        Không có sản phẩm nào phù hợp.
                    @endif
                </p>
                @if ($filters->isFiltered())
                    <a href="{{ route('products.index') }}" class="btn btn-secondary">Xóa bộ lọc</a>
                @endif
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
                                <img src="{{ \Illuminate\Support\Facades\Storage::disk(config('filesystems.image_disk'))->url($image->path) }}"
                                     alt="{{ $image->alt_text ?? $product->name }}" loading="lazy">
                            @else
                                <x-icon name="shirt" class="size-12" />
                            @endif
                        </div>
                        <div class="pg-body">
                            <p class="pg-brand">{{ $product->brand?->name ?? 'Fashion Store' }}</p>
                            <h3>{{ $product->name }}</h3>
                            <x-storefront.price-tag :product="$product" class="pg-price" />
                        </div>
                    </a>
                @endforeach
            </div>

            <div class="mt-8">{{ $products->links() }}</div>
        @endif
    </section>
@endsection
