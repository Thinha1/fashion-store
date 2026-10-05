@extends('layouts.app')
@section('title', 'Sản phẩm — ' . config('app.name', 'Fashion Store'))
@section('content')
    @php
        $state = array_filter([
            'category' => $activeCategory ?: null,
            'sort' => $sort !== 'moi-nhat' ? $sort : null,
            'brand' => $activeBrands ?: null,
            'price_min' => $priceMin,
            'price_max' => $priceMax,
            'size' => $activeSizes ?: null,
            'color' => $activeColors ?: null,
        ], fn ($value) => $value !== null);
        $listUrl = fn (array $overrides = [], array $remove = []) => route('products.index', array_diff_key([...$state, ...$overrides], array_flip($remove)));
        $withoutValue = fn (string $key, string $value) => route('products.index', [...$state, $key => array_values(array_diff($state[$key] ?? [], [$value]))]);
        $filterCount = count($activeBrands) + count($activeSizes) + count($activeColors) + ($priceMin || $priceMax ? 1 : 0);
        $brandNames = $brands->pluck('name', 'slug');
        $moneyLabel = fn (?int $value) => $value ? number_format($value, 0, ',', '.').' ₫' : null;

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
        $brandPreview = $brands->filter(fn ($brand, $index) => $index < 5 || in_array($brand->slug, $activeBrands, true));
        $brandMore = $brands->diffKeys($brandPreview);
    @endphp

    <section aria-labelledby="products-title"
             x-data="{ filtersOpen: false, priceMin: {{ Js::from((string) $priceMin) }}, priceMax: {{ Js::from((string) $priceMax) }}, moreBrands: false }">
        <div class="catalog-head">
            <h1 id="products-title" class="display-title">Sản phẩm</h1>
            <p class="catalog-count" role="status">{{ number_format($products->total(), 0, ',', '.') }} sản phẩm</p>
        </div>

        <form method="GET" action="{{ route('products.index') }}" class="catalog-bar" id="catalog-form">
            @if ($activeCategory)
                <input type="hidden" name="category" value="{{ $activeCategory }}">
            @endif
            <div class="catalog-bar-row">
                <div class="catalog-chips" role="group" aria-label="Danh mục">
                    <a href="{{ $listUrl(remove: ['category']) }}" class="catalog-chip @if (! $activeCategory) is-active @endif">Tất cả</a>
                    @foreach ($categories as $category)
                        <a href="{{ $listUrl(['category' => $category->slug]) }}"
                           class="catalog-chip @if ($activeL1?->id === $category->id) is-active @endif">{{ $category->name }}</a>
                    @endforeach
                </div>
                <button type="button" class="catalog-btn" x-on:click="filtersOpen = !filtersOpen" :aria-expanded="filtersOpen" aria-controls="catalog-sheet">
                    <x-icon name="sliders" class="size-4" /> Bộ lọc
                    @if ($filterCount)<span class="catalog-btn-count">{{ $filterCount }}</span>@endif
                </button>
                <label class="hidden items-center gap-2 text-[13px] text-gray-600 sm:flex">Sắp xếp
                    <select name="sort" class="catalog-select" onchange="this.form.requestSubmit()">
                        <option value="moi-nhat" @selected($sort === 'moi-nhat')>Mới nhất</option>
                        <option value="gia-tang" @selected($sort === 'gia-tang')>Giá tăng dần</option>
                        <option value="gia-giam" @selected($sort === 'gia-giam')>Giá giảm dần</option>
                    </select>
                </label>
            </div>

            @if ($activeL1 && $activeL1->children->isNotEmpty())
                <div class="catalog-bar-row">
                    <div class="catalog-chips" role="group" aria-label="Danh mục con">
                        <a href="{{ $listUrl(['category' => $activeL1->slug]) }}"
                           class="catalog-chip @if ($activeCategory === $activeL1->slug) is-active @endif">Tất cả {{ \Illuminate\Support\Str::lower($activeL1->name) }}</a>
                        @foreach ($activeL1->children as $child)
                            <a href="{{ $listUrl(['category' => $child->slug]) }}"
                               class="catalog-chip @if ($activeL2?->id === $child->id) is-active @endif">{{ $child->name }}</a>
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($activeL2 && $activeL2->children->isNotEmpty())
                <div class="catalog-bar-row">
                    <div class="catalog-chips" role="group" aria-label="Kiểu dáng">
                        <a href="{{ $listUrl(['category' => $activeL2->slug]) }}"
                           class="catalog-chip @if ($activeCategory === $activeL2->slug) is-active @endif">Tất cả {{ \Illuminate\Support\Str::lower($activeL2->name) }}</a>
                        @foreach ($activeL2->children as $style)
                            <a href="{{ $listUrl(['category' => $style->slug]) }}"
                               class="catalog-chip @if ($activeCategory === $style->slug) is-active @endif">{{ $style->name }}</a>
                        @endforeach
                    </div>
                </div>
            @endif

            <div id="catalog-sheet" class="catalog-sheet" x-show="filtersOpen" x-cloak x-transition.opacity.duration.150ms>
                <section>
                    <h2 class="catalog-sheet-title">Thương hiệu</h2>
                    @foreach ($brandPreview as $brand)
                        <label class="catalog-check"><input type="checkbox" name="brand[]" value="{{ $brand->slug }}" @checked(in_array($brand->slug, $activeBrands, true))> {{ $brand->name }}<b>{{ $brand->active_products_count }}</b></label>
                    @endforeach
                    @if ($brandMore->isNotEmpty())
                        <div x-show="moreBrands" x-cloak>
                            @foreach ($brandMore as $brand)
                                <label class="catalog-check"><input type="checkbox" name="brand[]" value="{{ $brand->slug }}"> {{ $brand->name }}<b>{{ $brand->active_products_count }}</b></label>
                            @endforeach
                        </div>
                        <button type="button" class="catalog-link" x-on:click="moreBrands = !moreBrands" x-text="moreBrands ? 'Thu gọn' : 'Xem thêm {{ $brandMore->count() }} thương hiệu'"></button>
                    @endif
                </section>
                <section>
                    <h2 class="catalog-sheet-title">Khoảng giá</h2>
                    <div class="catalog-range">
                        <input type="text" inputmode="numeric" name="price_min" x-model="priceMin" placeholder="Từ 0 ₫" aria-label="Giá từ">
                        <input type="text" inputmode="numeric" name="price_max" x-model="priceMax" placeholder="Đến 1.000.000 ₫" aria-label="Giá đến">
                    </div>
                    <div class="mt-2.5 flex flex-wrap gap-1.5">
                        <button type="button" class="catalog-chip !px-3" x-on:click="priceMin = ''; priceMax = '500000'" :aria-pressed="priceMin === '' && priceMax === '500000'">Dưới 500k</button>
                        <button type="button" class="catalog-chip !px-3" x-on:click="priceMin = '500000'; priceMax = '700000'" :aria-pressed="priceMin === '500000' && priceMax === '700000'">500k–700k</button>
                        <button type="button" class="catalog-chip !px-3" x-on:click="priceMin = '700000'; priceMax = ''" :aria-pressed="priceMin === '700000' && priceMax === ''">Trên 700k</button>
                    </div>
                </section>
                <section>
                    <h2 class="catalog-sheet-title">Size</h2>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($sizeOptions as $size)
                            <label class="variant-option has-[:checked]:border-brand has-[:checked]:bg-brand-soft has-[:checked]:text-brand has-[:focus-visible]:outline-3 has-[:focus-visible]:outline-brand has-[:focus-visible]:outline-offset-2">
                                <input type="checkbox" name="size[]" value="{{ $size }}" class="sr-only" @checked(in_array($size, $activeSizes, true))>{{ $size }}
                            </label>
                        @endforeach
                    </div>
                </section>
                <section>
                    <h2 class="catalog-sheet-title">Màu</h2>
                    <div class="flex flex-wrap gap-2.5">
                        @foreach ($colorOptions as $color)
                            <label class="color-swatch !size-8 has-[:checked]:ring-2 has-[:checked]:ring-brand has-[:checked]:ring-offset-2 has-[:focus-visible]:outline-3 has-[:focus-visible]:outline-brand has-[:focus-visible]:outline-offset-4"
                                   style="--swatch-color: {{ \App\Support\ColorSwatches::hex($color) }}" title="{{ $color }}">
                                <input type="checkbox" name="color[]" value="{{ $color }}" class="sr-only" aria-label="Màu {{ $color }}" @checked(in_array($color, $activeColors, true))>
                            </label>
                        @endforeach
                    </div>
                </section>
                <section class="sm:hidden">
                    <h2 class="catalog-sheet-title">Sắp xếp</h2>
                    <select class="catalog-select w-full" aria-label="Sắp xếp" onchange="window.location.href = this.value">
                        @foreach (['moi-nhat' => 'Mới nhất', 'gia-tang' => 'Giá tăng dần', 'gia-giam' => 'Giá giảm dần'] as $value => $label)
                            <option value="{{ $listUrl(['sort' => $value]) }}" @selected($sort === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </section>
                <div class="flex flex-wrap justify-end gap-2.5 sm:col-span-2 lg:col-span-4">
                    <a href="{{ $listUrl(remove: ['brand', 'price_min', 'price_max', 'size', 'color']) }}" class="btn btn-secondary">Xóa tất cả</a>
                    <button type="submit" class="btn btn-primary">Áp dụng</button>
                </div>
            </div>
        </form>

        @if ($filterCount)
            <div class="catalog-active" aria-label="Bộ lọc đang áp dụng">
                <span>Đang lọc:</span>
                @foreach ($activeBrands as $slug)
                    <a href="{{ $withoutValue('brand', $slug) }}" class="catalog-chip is-active" aria-label="Bỏ lọc thương hiệu {{ $brandNames[$slug] ?? $slug }}">{{ $brandNames[$slug] ?? $slug }} <x-icon name="close" class="size-3" /></a>
                @endforeach
                @if ($priceMin || $priceMax)
                    <a href="{{ $listUrl(remove: ['price_min', 'price_max']) }}" class="catalog-chip is-active" aria-label="Bỏ lọc giá">
                        {{ $priceMin && $priceMax ? $moneyLabel($priceMin).' – '.$moneyLabel($priceMax) : ($priceMin ? 'Từ '.$moneyLabel($priceMin) : 'Đến '.$moneyLabel($priceMax)) }} <x-icon name="close" class="size-3" />
                    </a>
                @endif
                @foreach ($activeSizes as $size)
                    <a href="{{ $withoutValue('size', $size) }}" class="catalog-chip is-active" aria-label="Bỏ lọc size {{ $size }}">Size {{ $size }} <x-icon name="close" class="size-3" /></a>
                @endforeach
                @foreach ($activeColors as $color)
                    <a href="{{ $withoutValue('color', $color) }}" class="catalog-chip is-active" aria-label="Bỏ lọc màu {{ $color }}">{{ $color }} <x-icon name="close" class="size-3" /></a>
                @endforeach
                <a href="{{ $listUrl(remove: ['brand', 'price_min', 'price_max', 'size', 'color']) }}" class="catalog-link">Xóa tất cả</a>
            </div>
        @endif

        <div class="mt-5">
            @if ($products->isEmpty())
                <div class="catalog-empty">
                    <span class="catalog-empty-icon"><x-icon name="search" /></span>
                    <h2 class="mt-4 mb-1 text-lg font-semibold">Chưa có sản phẩm khớp bộ lọc</h2>
                    <p class="mx-auto mb-5 max-w-sm text-sm text-gray-600">Thử bỏ bớt bộ lọc, hoặc mô tả món bạn cần để trợ lý gợi ý giúp.</p>
                    <div class="flex flex-wrap justify-center gap-2.5">
                        <a href="{{ $listUrl(remove: ['brand', 'price_min', 'price_max', 'size', 'color']) }}" class="btn btn-secondary">Xóa bộ lọc</a>
                        <button type="button" class="btn btn-primary" x-on:click="$dispatch('open-assistant')"><x-icon name="robot" class="size-4" /> Hỏi trợ lý</button>
                    </div>
                </div>
            @else
                <div class="catalog-grid">
                    @foreach ($products as $product)
                        <x-storefront.product-card :product="$product" />
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
