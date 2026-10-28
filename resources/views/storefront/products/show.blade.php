@extends('layouts.app')
@section('title', $product->name . ' — ' . config('app.name', 'Fashion Store'))
@section('content')
    @php
        $variantsForJs = $product->variants->map(fn ($v) => [
            'id' => $v->id,
            'size' => $v->size,
            'color' => $v->color,
            'price' => $variantPrices[$v->id]->unitPrice,
            'originalPrice' => $variantPrices[$v->id]->originalUnitPrice,
            'discountPercent' => $variantPrices[$v->id]->discountPercent(),
            'stock' => (int) $v->stock_quantity,
        ]);
        $imagesForJs = $product->images->map(fn ($image) => [
            'id' => $image->id,
            'url' => \Illuminate\Support\Facades\Storage::disk(config('filesystems.image_disk'))->url($image->path),
            'alt' => $image->alt_text ?: $product->name,
            'variantId' => $image->product_variant_id,
            'color' => $image->variant?->color,
        ])->values();
        $sizeOrder = array_flip(\App\Models\ProductVariant::SIZES);
        $sizes = $product->variants->pluck('size')->unique()->sortBy(fn ($size) => $sizeOrder[$size] ?? count($sizeOrder))->values();
        $colors = $product->variants->pluck('color')->unique()->values();
        // schema.org Product: search engines read it, and Anywear only opens on pages that carry one.
        $productJsonLd = array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $product->name,
            'description' => $product->description,
            'image' => $imagesForJs->pluck('url')->all(),
            'brand' => $product->brand ? ['@type' => 'Brand', 'name' => $product->brand->name] : null,
            'offers' => [
                '@type' => 'Offer',
                'url' => route('products.show', $product),
                'priceCurrency' => 'VND',
                'price' => $variantsForJs->min('price') ?? (float) $product->base_price,
                'availability' => $variantsForJs->sum('stock') > 0 ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
            ],
        ]);
    @endphp

    @push('head')
        <meta property="og:type" content="product">
        <meta property="og:title" content="{{ $product->name }}">
        @if ($imagesForJs->isNotEmpty())
            <meta property="og:image" content="{{ $imagesForJs->first()['url'] }}">
        @endif
        <script type="application/ld+json">{!! json_encode($productJsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
        @if (config('store.anywear_domain'))
            <script src="https://anywear.decart.ai/widget/latest/anywear.js?domain={{ urlencode(config('store.anywear_domain')) }}" async></script>
            {{-- Anywear hard-codes the English "Try-On" label on its image buttons; swap the text only. --}}
            <style>
                .vton-btn .vton-label { font-size: 0; }
                .vton-btn .vton-label::after { content: "Thử ngay"; font-size: 11px; }
            </style>
        @endif
    @endpush

    <div class="container page">
    <nav aria-label="Breadcrumb" class="crumbs">
        <ol>
            <li><a href="{{ route('home') }}">Trang chủ</a></li>
            <li><a href="{{ route('products.index') }}">Sản phẩm</a></li>
            @if ($product->category)
                <li><a href="{{ route('products.index', ['category' => $product->category->slug]) }}">{{ $product->category->name }}</a></li>
            @endif
            <li aria-current="page">{{ $product->name }}</li>
        </ol>
    </nav>

    <div class="pdp" x-data="productDetail({ variants: {{ Js::from($variantsForJs->values()) }}, images: {{ Js::from($imagesForJs) }} })">
        {{-- Gallery --}}
        <div class="pdp-gallery">
            <div class="pdp-thumbs" x-show="images.length > 1" x-cloak>
                <template x-for="(image, index) in images" :key="image.id">
                    <button type="button" class="gallery-thumb" :aria-current="(activeImage === index).toString()"
                            x-on:click="activeImage = index" :aria-label="'Xem ảnh ' + (index + 1)">
                        <img :src="image.url" alt="" width="160" height="200">
                    </button>
                </template>
            </div>

            <div class="pdp-stage" data-slot="pdp" data-kind="product" data-alt="{{ $product->name }}">
                @if ($imagesForJs->isNotEmpty())
                    <img src="{{ $imagesForJs->first()['url'] }}" :src="images[activeImage]?.url"
                         alt="{{ $imagesForJs->first()['alt'] }}" :alt="images[activeImage]?.alt"
                         width="1000" height="1250" fetchpriority="high">
                @endif
                <div class="pdp-stage-arrows" data-keep x-show="images.length > 1" x-cloak>
                    <button type="button" class="carousel-btn" x-on:click="prevImage()" aria-label="Ảnh trước"><x-icon name="chevron-left" /></button>
                    <button type="button" class="carousel-btn" x-on:click="nextImage()" aria-label="Ảnh sau"><x-icon name="chevron-right" /></button>
                </div>
            </div>
        </div>

        {{-- Info --}}
        <div class="pdp-info">
            <p class="pdp-brand">
                @if ($product->brand)
                    <a href="{{ route('collections.show', $product->brand) }}">{{ $product->brand->name }}</a>
                @else
                    {{ config('app.name', 'Fashion Store') }}
                @endif
            </p>
            <h1>{{ $product->name }}</h1>

            <div class="price pdp-price">
                <span class="price-now" x-text="(variant ? variant.price : {{ (float) $product->base_price }}).toLocaleString('vi-VN') + ' ₫'">{{ number_format((float) ($variantsForJs->min('price') ?? $product->base_price), 0, ',', '.') }} ₫</span>
                <template x-if="variant && variant.price < variant.originalPrice">
                    <span class="price">
                        <s class="price-was" x-text="variant.originalPrice.toLocaleString('vi-VN') + ' ₫'"></s>
                        <span class="tag" x-text="'-' + variant.discountPercent + '%'"></span>
                    </span>
                </template>
            </div>

            @if ($product->description)
                @if (mb_strlen($product->description) > 220)
                    <div class="pdp-desc" x-data="{ expanded: false }">
                        <p :class="expanded ? '' : 'pdp-clamp'">{{ $product->description }}</p>
                        <button type="button" class="text-link" x-on:click="expanded = !expanded" x-text="expanded ? 'Thu gọn' : 'Xem thêm'"
                                :aria-expanded="expanded.toString()">Xem thêm</button>
                    </div>
                @else
                    <p class="pdp-desc">{{ $product->description }}</p>
                @endif
            @endif

            @if ($colors->isNotEmpty())
                <div class="pdp-option">
                    <p class="pdp-option-head">Màu <span x-text="selectedColor"></span></p>
                    <div class="pdp-swatches">
                        @foreach ($colors as $color)
                            <button type="button" class="color-swatch" style="--swatch-color: {{ \App\Support\ColorPalette::hex($color) }}"
                                    x-on:click="pickColor({{ Js::from($color) }})"
                                    :aria-pressed="(selectedColor === {{ Js::from($color) }}).toString()"
                                    aria-label="Màu {{ $color }}" title="{{ $color }}"></button>
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($sizes->isNotEmpty())
                <div class="pdp-option">
                    <div class="pdp-option-row">
                        <p class="pdp-option-head">Size</p>
                        <a href="#" class="text-link" x-on:click.prevent="$dispatch('notify', 'Bảng size đang được cập nhật.')">Xem hướng dẫn chọn size</a>
                    </div>
                    <div class="pdp-sizes">
                        @foreach ($sizes as $size)
                            <button type="button" class="variant-option"
                                    x-on:click="pickSize({{ Js::from($size) }})"
                                    :aria-pressed="(selectedSize === {{ Js::from($size) }}).toString()"
                                    :disabled="!hasVariant({{ Js::from($size) }}, selectedColor)">
                                {{ $size }}
                            </button>
                        @endforeach
                    </div>
                </div>
            @endif

            <p class="pdp-stock" :class="inStock ? 'is-in' : 'is-out'"
               x-text="inStock ? 'Còn ' + maxQty + ' sản phẩm' : 'Hết hàng với lựa chọn này'"></p>

            <div class="pdp-buy">
                <div class="stepper">
                    <button type="button" x-on:click="dec()" aria-label="Giảm số lượng"><x-icon name="minus" /></button>
                    <span class="num" x-text="qty" aria-live="polite">1</span>
                    <button type="button" x-on:click="inc()" aria-label="Tăng số lượng"><x-icon name="plus" /></button>
                </div>
                <form method="POST" action="{{ route('cart.store') }}" class="pdp-add">
                    @csrf
                    <input type="hidden" name="product_variant_id" :value="variant ? variant.id : ''">
                    <input type="hidden" name="quantity" :value="qty">
                    {{-- data-testid lets Anywear's in-panel "add to bag" find this button. --}}
                    <button type="submit" class="btn btn-primary" :disabled="!inStock" data-testid="add-to-cart">
                        <x-icon name="cart" /> Thêm vào giỏ
                    </button>
                </form>
            </div>
            <x-input-error :messages="$errors->get('product_variant_id')" />
            <x-input-error :messages="$errors->get('quantity')" />
            <p class="pdp-notify" role="status"
               x-data="{ msg: '', timer: null }"
               x-on:notify.window="msg = $event.detail; clearTimeout(timer); timer = setTimeout(() => msg = '', 3500)"
               x-show="msg" x-text="msg" x-cloak></p>

            <dl class="pdp-facts">
                <div><dt>Danh mục</dt><dd>{{ $product->category?->name ?? 'Chưa phân loại' }}</dd></div>
                <div><dt>Thương hiệu</dt><dd>{{ $product->brand?->name ?? config('app.name', 'Fashion Store') }}</dd></div>
            </dl>
        </div>
    </div>
    </div>

    @if ($related->isNotEmpty())
        <section class="section" aria-labelledby="related-title">
            <div class="container">
                <div class="section-head opens">
                    <h2 id="related-title">Cùng thương hiệu {{ $product->brand?->name }}</h2>
                    @if ($product->brand)
                        <a href="{{ route('collections.show', $product->brand) }}" class="text-link">Xem bộ sưu tập {{ $product->brand->name }}</a>
                    @endif
                </div>
                <ul class="pgrid" role="list">
                    @foreach ($related as $item)
                        <li><x-storefront.product-card :product="$item" /></li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif
@endsection
