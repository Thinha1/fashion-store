@extends('layouts.app')
@section('title', 'Fashion Store — Phong cách của riêng bạn')
@section('content')
    @php
        $imageUrl = fn ($product) => ($image = $product->images->first())
            ? \Illuminate\Support\Facades\Storage::disk(config('filesystems.image_disk'))->url($image->path)
            : null;
    @endphp

    <section class="cover" aria-labelledby="hero-title">
        <div class="container cover-grid">
            <div class="cover-text">
                <h1 id="hero-title">Mặc đẹp theo cách của bạn.</h1>
                <p class="lead">Từ một ngày đi làm đến buổi hẹn cuối tuần.</p>
                <div class="cover-actions">
                    <a href="{{ route('products.index') }}" class="btn btn-primary">Xem tất cả sản phẩm</a>
                    <a href="#phong-cach" class="text-link">Khám phá phong cách</a>
                </div>
            </div>

            @if ($cover->isNotEmpty())
                {{-- The cover: one featured product large, the next three beside
                     it. The arrows move through the six; nothing turns on its own.
                     Without script the first product stays on the cover. --}}
                <div class="cover-visual" x-data="coverCarousel({ count: {{ $cover->count() }} })">
                    <div class="cover-stage">
                        @foreach ($cover as $product)
                            <a href="{{ route('products.show', $product) }}" class="cover-lead"
                               x-show="active === {{ $loop->index }}" @unless ($loop->first) style="display: none" @endunless
                               x-transition:enter="cover-fade" x-transition:enter-start="cover-fade-from">
                                <div data-slot="cover-lead-{{ $loop->index }}" data-kind="product" data-alt="{{ $product->name }}">
                                    @if ($url = $imageUrl($product))
                                        <img src="{{ $url }}" alt="{{ $product->images->first()->alt_text ?: $product->name }}" width="800" height="1000"
                                             @if ($loop->first) fetchpriority="high" @else loading="lazy" @endif>
                                    @endif
                                </div>
                                <span class="cover-line">
                                    <strong>{{ $product->name }}</strong>
                                    <x-storefront.price-tag :product="$product" />
                                </span>
                            </a>
                        @endforeach
                    </div>
                    <div class="cover-side">
                        @if ($cover->count() > 1)
                            <div class="cover-nav" x-cloak>
                                <span class="caption">Sản phẩm nổi bật</span>
                                <div class="carousel-arrows">
                                    <button type="button" class="carousel-btn" x-on:click="prev()" aria-label="Sản phẩm nổi bật trước"><x-icon name="chevron-left" /></button>
                                    <button type="button" class="carousel-btn" x-on:click="next()" aria-label="Sản phẩm nổi bật tiếp theo"><x-icon name="chevron-right" /></button>
                                </div>
                            </div>
                        @endif
                        <ul class="cover-index" role="list" aria-label="Sản phẩm nổi bật tiếp theo">
                            @foreach ($cover as $item)
                                <li x-show="upNext({{ $loop->index }})" :style="{ order: position({{ $loop->index }}) }"
                                    @unless ($loop->index >= 1 && $loop->index <= 3) style="display: none" @endunless>
                                    <a href="{{ route('products.show', $item) }}" class="cover-item">
                                        <div data-slot="cover-{{ $loop->index }}" data-kind="product" data-alt="{{ $item->name }}">
                                            @if ($url = $imageUrl($item))
                                                <img src="{{ $url }}" alt="" width="800" height="1000" loading="lazy">
                                            @endif
                                        </div>
                                        <span class="pcard-body">
                                            <span class="pcard-brand">{{ $item->brand?->name ?? config('app.name', 'Fashion Store') }}</span>
                                            <span class="pcard-name" title="{{ $item->name }}">{{ \Illuminate\Support\Str::limit($item->name, 40, '…') }}</span>
                                            <x-storefront.price-tag :product="$item" />
                                        </span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                        <p class="visually-hidden" aria-live="polite" x-text="status"></p>
                    </div>
                </div>
            @endif
        </div>
    </section>

    @include('storefront.home._shelf', [
        'id' => 'featured', 'shelf' => 'noi-bat', 'page' => $shelves['noi-bat'], 'except' => $coverExcept,
        'title' => 'Sản phẩm nổi bật', 'moreUrl' => route('products.index'), 'moreLabel' => 'Xem tất cả sản phẩm',
    ])

    @if ($categoryGroups->isNotEmpty())
        <section id="phong-cach" class="section" aria-labelledby="style-title">
            <div class="container index">
                <div class="section-head opens">
                    <h2 id="style-title">Mua theo danh mục</h2>
                    <p>{{ $categoryGroups->sum('products_total') }} sản phẩm, chia theo áo, quần và váy cho nam và nữ.</p>
                </div>
                <ul class="index-rows" role="list">
                    @foreach ($categoryGroups as $group)
                        <li class="index-row">
                            <h3><a href="{{ route('products.index', ['category' => $group->slug]) }}">{{ $group->name }}</a></h3>
                            @if ($group->children->isNotEmpty())
                                <ul class="index-leaves" role="list" aria-label="{{ $group->name }}">
                                    @foreach ($group->children->where('products_total', '>', 0) as $leaf)
                                        <li><a href="{{ route('products.index', ['category' => $leaf->slug]) }}">{{ $leaf->name }}</a></li>
                                    @endforeach
                                </ul>
                            @endif
                            <span class="index-count">{{ $group->products_total }}<small>sản phẩm</small></span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif

    @include('storefront.home._shelf', [
        'id' => 'sale', 'shelf' => 'giam-gia', 'page' => $shelves['giam-gia'],
        'title' => 'Đang giảm giá', 'moreUrl' => route('products.index', ['sort' => 'gia-tang']), 'moreLabel' => 'Xem theo giá',
    ])

    @if ($brands->isNotEmpty())
        <section class="band-ink" aria-labelledby="brands-title">
            <div class="container">
                <div class="section-head">
                    <h2 id="brands-title">{{ $brands->count() }} thương hiệu, một giỏ hàng</h2>
                    <a href="{{ route('collections.index') }}" class="slab-cta slab-cta--quiet">Xem bộ sưu tập</a>
                </div>
                <ul class="brands" role="list">
                    @foreach ($brands as $brand)
                        <li>
                            <a href="{{ route('collections.show', $brand) }}">
                                <strong>{{ $brand->name }}</strong>
                                <span>{{ $brand->products_count }} sản phẩm</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif

    @include('storefront.home._shelf', [
        'id' => 'latest', 'shelf' => 'moi-ve', 'page' => $shelves['moi-ve'],
        'title' => 'Mới về', 'moreUrl' => route('products.index', ['sort' => 'moi-nhat']), 'moreLabel' => 'Xem hàng mới',
    ])
@endsection
