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

    <div class="container page"
             x-data="{ density: ['3', '4', '5'].includes(localStorage.getItem('catalogDensityCompact')) ? localStorage.getItem('catalogDensityCompact') : '4' }"
             x-init="$watch('density', value => localStorage.setItem('catalogDensityCompact', value))">
        <header class="catalog-header">
        <div class="catalog-head">
            <div class="catalog-title">
                <h1 id="products-title">
                    @if ($filters->q !== '')
                        Kết quả cho “{{ $filters->q }}”
                    @else
                        Sản phẩm
                    @endif
                </h1>
                <p class="caption">{{ $products->total() }} sản phẩm</p>
            </div>
            <form method="GET" action="{{ route('products.index') }}" role="search" class="catalog-search"
                  x-data="{ searchingImage: false }" x-init="if (location.hash === '#tim-kiem') $refs.q.focus()"
                  x-on:hashchange.window="if (location.hash === '#tim-kiem') $refs.q.focus()">
                @include('storefront.products._filter-inputs', ['query' => \Illuminate\Support\Arr::except($filters->query(), ['q', 'sort'])])
                <label for="tim-kiem" class="visually-hidden">Tìm sản phẩm</label>
                <input id="tim-kiem" x-ref="q" type="search" name="q" value="{{ $filters->q }}" maxlength="100"
                       placeholder="Tìm áo, quần, thương hiệu…" autocomplete="off" class="field"
                       :placeholder="searchingImage ? 'Đang tìm bằng ảnh…' : 'Tìm áo, quần, thương hiệu…'" :disabled="searchingImage">
                {{-- The photo input belongs to the POST form below (form="…"),
                     not to this GET search form. --}}
                <label for="tim-bang-anh" class="btn btn-secondary catalog-photo" title="Tìm bằng hình ảnh"
                       :aria-busy="searchingImage.toString()">
                    <x-icon name="camera" x-show="!searchingImage" />
                    <span x-cloak x-show="searchingImage" class="catalog-spinner" aria-hidden="true"></span>
                    <span class="visually-hidden">Tìm bằng hình ảnh</span>
                </label>
                <input id="tim-bang-anh" type="file" name="image" form="image-search-form" accept="image/jpeg,image/png,image/webp"
                       class="visually-hidden" x-on:change="if ($el.files.length) { searchingImage = true; $el.form.requestSubmit(); }">
                <button type="submit" class="btn btn-primary" :disabled="searchingImage">Tìm</button>
            </form>
            <form id="image-search-form" method="POST" action="{{ route('products.image-search') }}" enctype="multipart/form-data" hidden>
                @csrf
            </form>
            @error('image')
                <p class="catalog-error" role="alert">{{ $message }}</p>
            @enderror
        </div>

        <div class="catalog-bar">
            <nav class="catalog-tabs" aria-label="Danh mục">
                <a href="{{ route('products.index', $filters->query(['category' => null])) }}" class="catalog-tab" @if (! $activeCategory) aria-current="page" @endif>Tất cả</a>
                @foreach ($categories as $category)
                    <a href="{{ route('products.index', $filters->query(['category' => $category->slug])) }}"
                       class="catalog-tab" @if ($activeL1?->id === $category->id) aria-current="page" @endif>{{ $category->name }}</a>
                @endforeach
            </nav>

            @if ($activeL1 && $activeL1->children->isNotEmpty())
                <nav class="catalog-chips" aria-label="Nhóm trong {{ \Illuminate\Support\Str::lower($activeL1->name) }}">
                    <a href="{{ route('products.index', $filters->query(['category' => $activeL1->slug])) }}"
                       class="catalog-chip" @if ($activeCategory === $activeL1->slug) aria-current="page" @endif>Tất cả {{ \Illuminate\Support\Str::lower($activeL1->name) }}</a>
                    @foreach ($activeL1->children as $child)
                        <a href="{{ route('products.index', $filters->query(['category' => $child->slug])) }}"
                           class="catalog-chip" @if ($activeL2?->id === $child->id) aria-current="page" @endif>{{ $child->name }}</a>
                    @endforeach
                </nav>
            @endif

            @if ($activeL2 && $activeL2->children->isNotEmpty())
                <nav class="catalog-chips" aria-label="Loại trong {{ \Illuminate\Support\Str::lower($activeL2->name) }}">
                    <a href="{{ route('products.index', $filters->query(['category' => $activeL2->slug])) }}"
                       class="catalog-chip" @if ($activeCategory === $activeL2->slug) aria-current="page" @endif>Tất cả {{ \Illuminate\Support\Str::lower($activeL2->name) }}</a>
                    @foreach ($activeL2->children as $style)
                        <a href="{{ route('products.index', $filters->query(['category' => $style->slug])) }}"
                           class="catalog-chip" @if ($activeCategory === $style->slug) aria-current="page" @endif>{{ $style->name }}</a>
                    @endforeach
                </nav>
            @endif

            <div class="catalog-tools">
                <div class="catalog-filters" x-data="{ open: null }" x-on:keydown.escape="open = null">
                    @foreach ($dropdowns as $key => $dropdown)
                        @continue($dropdown['options']->isEmpty())
                        <div class="catalog-dropdown" x-on:click.outside="if (open === '{{ $key }}') open = null">
                            <button type="button" class="catalog-filter @if ($dropdown['active']) is-active @endif"
                                    aria-controls="filter-{{ $key }}" :aria-expanded="(open === '{{ $key }}').toString()" aria-expanded="false"
                                    x-on:click="open = open === '{{ $key }}' ? null : '{{ $key }}'">
                                {{ $dropdown['label'] }} <x-icon name="chevron-down" />
                            </button>
                            <div id="filter-{{ $key }}" x-cloak x-show="open === '{{ $key }}'" class="catalog-dropdown-panel">
                                <a href="{{ route('products.index', $filters->query($dropdown['clear'])) }}"
                                   class="catalog-chip" @if (! $dropdown['active']) aria-current="true" @endif>Tất cả</a>
                                @foreach ($dropdown['options'] as $option)
                                    <a href="{{ route('products.index', $option['query']) }}"
                                       class="catalog-chip" @if ($option['active']) aria-current="true" @endif>@isset($option['swatch'])<span class="catalog-swatch" style="--swatch-color: {{ $option['swatch'] }}" aria-hidden="true"></span>@endisset{{ $option['label'] }}</a>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                    <a href="{{ route('products.index', $filters->query(['in_stock' => $filters->inStock ? null : 1])) }}"
                       class="catalog-filter @if ($filters->inStock) is-active @endif" @if ($filters->inStock) aria-current="true" @endif>
                        @if ($filters->inStock)<x-icon name="check" />@endif Còn hàng
                    </a>
                    @if ($filters->isFiltered())
                        <a href="{{ route('products.index') }}" class="text-link">Xóa bộ lọc</a>
                    @endif
                </div>
                <div class="catalog-view">
                    <form method="GET" action="{{ route('products.index') }}" class="catalog-sort">
                        @include('storefront.products._filter-inputs', ['query' => \Illuminate\Support\Arr::except($filters->query(), ['sort'])])
                        <label for="sort">Sắp xếp</label>
                        <select id="sort" name="sort" onchange="this.form.submit()" class="field">
                            @if ($filters->q !== '')
                                <option value="lien-quan" @selected($filters->sort === 'lien-quan')>Phù hợp nhất</option>
                            @endif
                            <option value="moi-nhat" @selected($filters->sort === 'moi-nhat')>Mới nhất</option>
                            <option value="gia-tang" @selected($filters->sort === 'gia-tang')>Giá tăng dần</option>
                            <option value="gia-giam" @selected($filters->sort === 'gia-giam')>Giá giảm dần</option>
                        </select>
                    </form>
                    <fieldset class="catalog-density" x-cloak>
                        <legend class="visually-hidden">Số cột sản phẩm</legend>
                        <button type="button" class="density-btn" :aria-pressed="(density === '3').toString()" x-on:click="density = '3'" aria-label="Thẻ lớn, 3 cột" title="Thẻ lớn">3</button>
                        <button type="button" class="density-btn" :aria-pressed="(density === '4').toString()" x-on:click="density = '4'" aria-label="Thẻ vừa, 4 cột" title="Thẻ vừa">4</button>
                        <button type="button" class="density-btn" :aria-pressed="(density === '5').toString()" x-on:click="density = '5'" aria-label="Thẻ nhỏ, 5 cột" title="Thẻ nhỏ">5</button>
                    </fieldset>
                </div>
            </div>
        </div>

        </header>

        <section aria-labelledby="products-title">
        @if ($products->isEmpty())
            <div class="catalog-empty">
                <h2>
                    @if ($filters->q !== '')
                        Không tìm thấy sản phẩm nào cho “{{ $filters->q }}”.
                    @else
                        Không có sản phẩm nào phù hợp.
                    @endif
                </h2>
                <p>Thử một từ khác, hoặc bỏ bớt bộ lọc để xem thêm mẫu.</p>
                @if ($filters->isFiltered())
                    <a href="{{ route('products.index') }}" class="btn btn-primary">Xóa bộ lọc</a>
                @endif
            </div>
        @else
            <ul class="pgrid catalog-grid" role="list" data-density="4" :data-density="density">
                @foreach ($products as $product)
                    <li>
                        <x-storefront.product-card :product="$product"
                            :badge="(int) $product->stock_total === 0 ? 'Hết hàng' : ($product->is_featured ? 'Nổi bật' : null)" />
                    </li>
                @endforeach
            </ul>

            <div class="catalog-pages">{{ $products->links('components.pagination-links') }}</div>
        @endif
        </section>
    </div>
@endsection
