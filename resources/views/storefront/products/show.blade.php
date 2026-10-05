@extends('layouts.app')
@section('title', $product->name . ' — ' . config('app.name', 'Fashion Store'))
@section('content')
    @php
        $variantsForJs = $product->variants->map(fn ($v) => [
            'id' => $v->id,
            'size' => $v->size,
            'color' => $v->color,
            'price' => (float) ($v->price ?? $product->base_price),
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
        $priceExpression = '(variant ? variant.price : '.(float) $product->base_price.').toLocaleString(\'vi-VN\') + \' ₫\'';
    @endphp

    <nav aria-label="Đường dẫn" class="mb-5 flex flex-wrap items-center gap-2 text-[13px] text-gray-600">
        <a href="{{ route('home') }}" class="py-1.5 hover:text-brand">Trang chủ</a>
        <x-icon name="chevron-right" class="size-2.5 text-gray-400" />
        <a href="{{ route('products.index') }}" class="py-1.5 hover:text-brand">Sản phẩm</a>
        <x-icon name="chevron-right" class="size-2.5 text-gray-400" />
        <span class="text-gray-900" aria-current="page">{{ \Illuminate\Support\Str::limit($product->name, 40) }}</span>
    </nav>

    <div class="pd-grid"
         x-data="productDetail({ variants: {{ Js::from($variantsForJs->values()) }}, images: {{ Js::from($imagesForJs) }} })">
        {{-- Gallery --}}
        <div class="pd-gallery">
            <div class="pd-main">
                <template x-if="images.length">
                    <img :src="images[activeImage]?.url" :alt="images[activeImage]?.alt"
                         x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0"
                         x-transition:enter-end="opacity-100" :key="activeImage">
                </template>
                <template x-if="!images.length">
                    <div class="pd-noimg"><x-icon name="shirt" /><p>Chưa có ảnh cho sản phẩm này</p></div>
                </template>

                <template x-if="images.length > 1">
                    <button type="button" x-on:click="prevImage()" aria-label="Ảnh trước" class="pd-arrow left-3">
                        <x-icon name="chevron-left" class="size-3.5" />
                    </button>
                </template>
                <template x-if="images.length > 1">
                    <button type="button" x-on:click="nextImage()" aria-label="Ảnh sau" class="pd-arrow right-3">
                        <x-icon name="chevron-right" class="size-3.5" />
                    </button>
                </template>
            </div>

            <div class="pd-thumbs" x-show="images.length > 1" x-cloak>
                <template x-for="(image, index) in images.slice(0, 6)" :key="image.id">
                    <button type="button" class="gallery-thumb" :aria-current="activeImage === index"
                            x-on:click="activeImage = index" :aria-label="'Xem ảnh ' + (index + 1)">
                        <img :src="image.url" alt="">
                    </button>
                </template>
            </div>
        </div>

        {{-- Buy panel --}}
        <div class="pd-buy">
            <div>
                @if ($product->brand)
                    <a href="{{ route('collections.show', $product->brand) }}" class="inline-block py-1 text-[11px] font-semibold tracking-[0.12em] text-gray-600 uppercase hover:text-brand">{{ $product->brand->name }}</a>
                @else
                    <span class="inline-block text-[11px] font-semibold tracking-[0.12em] text-gray-600 uppercase">Fashion Store</span>
                @endif
                <h1>{{ $product->name }}</h1>
            </div>

            <p class="pd-price" x-text="{{ $priceExpression }}">{{ number_format((float) $product->base_price, 0, ',', '.') }} ₫</p>

            @if ($colors->isNotEmpty())
                <div>
                    <p class="pd-opt-head"><span>Màu <span class="font-normal text-gray-600" x-text="selectedColor"></span></span></p>
                    <div class="flex flex-wrap gap-2.5">
                        @foreach ($colors as $color)
                            <button type="button" class="color-swatch !size-8" style="--swatch-color: {{ \App\Support\ColorSwatches::hex($color) }}"
                                    x-on:click="pickColor({{ Js::from($color) }})"
                                    :aria-pressed="selectedColor === {{ Js::from($color) }}"
                                    aria-label="Màu {{ $color }}"></button>
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($sizes->isNotEmpty())
                <div>
                    <div class="pd-opt-head">
                        <span>Size</span>
                        <a href="#" class="-my-1.5 py-1.5" x-on:click.prevent="$dispatch('notify', 'Bảng size đang được cập nhật.')">Hướng dẫn chọn size</a>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($sizes as $size)
                            <button type="button" class="variant-option"
                                    x-on:click="pickSize({{ Js::from($size) }})"
                                    :aria-pressed="selectedSize === {{ Js::from($size) }}"
                                    :disabled="!hasVariant({{ Js::from($size) }}, selectedColor)">
                                {{ $size }}
                            </button>
                        @endforeach
                    </div>
                    <p class="mt-2.5 text-[13px] font-semibold" :class="!inStock ? 'text-gray-600' : (maxQty < 20 ? 'text-amber-700' : 'text-green-700')"
                       x-text="!inStock ? 'Tạm hết hàng với lựa chọn này' : (maxQty < 20 ? 'Chỉ còn ' + maxQty + ' sản phẩm' : 'Còn hàng')"></p>
                </div>
            @endif

            <div class="flex gap-3">
                <div class="pd-qty">
                    <button type="button" class="flex size-11 items-center justify-center text-lg text-gray-700 hover:text-brand" x-on:click="dec()" aria-label="Giảm số lượng">−</button>
                    <span class="min-w-7 text-center text-sm font-bold" x-text="qty" aria-live="polite"></span>
                    <button type="button" class="flex size-11 items-center justify-center text-lg text-gray-700 hover:text-brand" x-on:click="inc()" aria-label="Tăng số lượng">+</button>
                </div>
                {{-- Giỏ hàng chưa có: bấm chỉ hiện thông báo, để bạn nối logic thêm vào giỏ ở đây. --}}
                <button type="button" class="btn btn-primary min-h-12 flex-1" :disabled="!inStock"
                        x-on:click="$dispatch('notify', 'Giỏ hàng sẽ sớm ra mắt — cảm ơn bạn đã quan tâm!')">
                    <x-icon name="bag" class="size-4" /> <span x-text="inStock ? 'Thêm vào giỏ' : 'Hết hàng'">Thêm vào giỏ</span>
                </button>
            </div>

            <button type="button" class="pd-ask" x-on:click="$dispatch('open-assistant')">
                <span class="pd-avatar"><x-icon name="robot" /></span>
                <span><b class="block font-bold">Hỏi trợ lý về sản phẩm này</b><small class="mt-0.5 block text-xs text-gray-600">VD: size nào hợp 1m65, 55kg? Phối với quần gì?</small></span>
            </button>

            <div class="pd-acc">
                @if ($product->description)
                    <details open>
                        <summary>Mô tả <x-icon name="chevron-down" /></summary>
                        <div class="pb-[18px] text-sm leading-[1.7] whitespace-pre-line text-gray-700">{{ $product->description }}</div>
                    </details>
                @endif
                <details @if (! $product->description) open @endif>
                    <summary>Thông tin <x-icon name="chevron-down" /></summary>
                    <dl class="grid grid-cols-[110px_1fr] gap-x-3 gap-y-2 pb-[18px] text-sm text-gray-700">
                        <dt class="text-gray-600">Danh mục</dt><dd>{{ $product->category?->name ?? '—' }}</dd>
                        <dt class="text-gray-600">Thương hiệu</dt><dd>{{ $product->brand?->name ?? '—' }}</dd>
                    </dl>
                </details>
            </div>
        </div>

        <div class="pd-sticky-buy" aria-label="Mua nhanh">
            <div class="min-w-0">
                <p class="truncate text-xs text-gray-600">{{ \Illuminate\Support\Str::limit($product->name, 28) }}</p>
                <p class="text-[17px] font-bold" x-text="{{ $priceExpression }}">{{ number_format((float) $product->base_price, 0, ',', '.') }} ₫</p>
            </div>
            <button type="button" class="btn btn-primary min-h-12 flex-1" :disabled="!inStock"
                    x-on:click="$dispatch('notify', 'Giỏ hàng sẽ sớm ra mắt — cảm ơn bạn đã quan tâm!')"
                    x-text="inStock ? 'Thêm vào giỏ' : 'Hết hàng'">Thêm vào giỏ</button>
        </div>
    </div>
    <div class="h-16 sm:hidden" aria-hidden="true"></div>

    @if ($related->isNotEmpty())
        <section class="mt-16" aria-labelledby="related-title">
            <h2 id="related-title" class="display-title mb-5 text-2xl">Cùng thương hiệu {{ $product->brand?->name }}</h2>
            <div class="catalog-grid">
                @foreach ($related as $item)
                    <x-storefront.product-card :product="$item" />
                @endforeach
            </div>
        </section>
    @endif
@endsection
