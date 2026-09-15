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
        $firstInStock = $variantsForJs->firstWhere('stock', '>', 0) ?? $variantsForJs->first();
        $colorSwatches = ['Đen' => '#17343a', 'Trắng' => '#ffffff'];
        $sizes = $product->variants->pluck('size')->unique()->values();
        $colors = $product->variants->pluck('color')->unique()->values();
    @endphp

    <nav aria-label="Breadcrumb" class="mb-6 flex flex-wrap items-center gap-1.5 text-xs text-gray-500">
        <a href="{{ route('home') }}" class="hover:text-brand">Trang chủ</a>
        <x-icon name="arrow" class="size-2.5 rotate-0" />
        <a href="{{ route('products.index') }}" class="hover:text-brand">Sản phẩm</a>
        <x-icon name="arrow" class="size-2.5 rotate-0" />
        <span class="text-gray-900">{{ $product->name }}</span>
    </nav>

    <div class="grid gap-10 lg:grid-cols-[minmax(0,1fr)_minmax(0,26rem)]"
         x-data="{
            images: {{ $product->images->pluck('path')->values()->toJson() }},
            activeImage: 0,
            variants: {{ $variantsForJs->values()->toJson() }},
            selectedSize: {{ Js::from($firstInStock['size'] ?? null) }},
            selectedColor: {{ Js::from($firstInStock['color'] ?? null) }},
            qty: 1,
            get variant() { return this.variants.find(v => v.size === this.selectedSize && v.color === this.selectedColor) ?? null; },
            get inStock() { return this.variant ? this.variant.stock > 0 : false; },
            get maxQty() { return this.variant ? this.variant.stock : 0; },
            hasVariant(size, color) { return this.variants.some(v => v.size === size && v.color === color); },
            pickSize(size) {
                this.selectedSize = size;
                if (!this.hasVariant(size, this.selectedColor)) {
                    const match = this.variants.find(v => v.size === size);
                    if (match) this.selectedColor = match.color;
                }
                this.qty = 1;
            },
            pickColor(color) {
                this.selectedColor = color;
                if (!this.hasVariant(this.selectedSize, color)) {
                    const match = this.variants.find(v => v.color === color);
                    if (match) this.selectedSize = match.size;
                }
                this.qty = 1;
            },
            prevImage() { this.activeImage = (this.activeImage - 1 + images.length) % images.length; },
            nextImage() { this.activeImage = (this.activeImage + 1) % images.length; },
            inc() { if (this.qty < this.maxQty) this.qty++; },
            dec() { if (this.qty > 1) this.qty--; },
         }">
        {{-- Gallery --}}
        <div class="flex gap-3">
            <div class="hidden shrink-0 flex-col gap-3 sm:flex" x-show="images.length > 1">
                <template x-for="(path, index) in images" :key="index">
                    <button type="button" class="gallery-thumb w-16" :aria-current="activeImage === index"
                            x-on:click="activeImage = index" aria-label="Xem ảnh">
                        <img :src="'{{ rtrim(config('filesystems.disks.s3.url'), '/') }}/' + path" alt="">
                    </button>
                </template>
            </div>

            <div class="product-card-media relative min-w-0 flex-1 rounded-2xl" style="aspect-ratio: 4 / 5;">
                <template x-if="images.length">
                    <img :src="'{{ rtrim(config('filesystems.disks.s3.url'), '/') }}/' + images[activeImage]"
                         :alt="{{ Js::from($product->name) }}" class="size-full object-cover"
                         x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0"
                         x-transition:enter-end="opacity-100" :key="activeImage">
                </template>
                <template x-if="!images.length">
                    <x-icon name="shirt" class="size-16" />
                </template>

                <template x-if="images.length > 1">
                    <button type="button" x-on:click="prevImage()" aria-label="Ảnh trước"
                            class="absolute top-1/2 left-3 flex size-9 -translate-y-1/2 items-center justify-center rounded-full bg-white/90 text-gray-700 shadow-sm hover:text-brand">
                        <x-icon name="arrow" class="size-3.5 rotate-180" />
                    </button>
                </template>
                <template x-if="images.length > 1">
                    <button type="button" x-on:click="nextImage()" aria-label="Ảnh sau"
                            class="absolute top-1/2 right-3 flex size-9 -translate-y-1/2 items-center justify-center rounded-full bg-white/90 text-gray-700 shadow-sm hover:text-brand">
                        <x-icon name="arrow" class="size-3.5" />
                    </button>
                </template>
            </div>
        </div>

        {{-- Info --}}
        <div>
            <h1 class="text-xl font-semibold text-gray-900">{{ $product->name }}</h1>
            <p class="mt-1.5 flex items-center gap-1.5 text-sm text-gray-500">
                <x-icon name="tag" class="size-3.5 text-gray-400" />
                Thương hiệu
                @if ($product->brand)
                    <a href="{{ route('collections.show', $product->brand) }}" class="font-medium text-gray-900 hover:text-brand hover:underline">{{ $product->brand->name }}</a>
                @else
                    <span class="font-medium text-gray-900">Fashion Store</span>
                @endif
            </p>

            <p class="mt-4 text-2xl font-semibold text-gray-900"
               x-text="(variant ? variant.price : {{ (float) $product->base_price }}).toLocaleString('vi-VN') + ' ₫'"></p>

            @if ($product->description)
                <p class="mt-4 max-w-md text-sm leading-7 text-gray-600">{{ $product->description }}</p>
            @endif

            @if ($colors->isNotEmpty())
                <div class="mt-7">
                    <p class="text-sm font-medium text-gray-900">Màu <span class="font-normal text-gray-500" x-text="selectedColor"></span></p>
                    <div class="mt-2.5 flex flex-wrap gap-2.5">
                        @foreach ($colors as $color)
                            <button type="button" class="color-swatch" style="--swatch-color: {{ $colorSwatches[$color] ?? '#e5e7eb' }}"
                                    x-on:click="pickColor('{{ $color }}')"
                                    :aria-pressed="selectedColor === '{{ $color }}'"
                                    aria-label="Màu {{ $color }}"></button>
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($sizes->isNotEmpty())
                <div class="mt-6">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-medium text-gray-900">Size</p>
                        <a href="#" class="text-xs text-brand hover:underline" x-on:click.prevent="$dispatch('notify', 'Bảng size đang được cập nhật.')">Xem hướng dẫn chọn size</a>
                    </div>
                    <div class="mt-2.5 flex flex-wrap gap-2">
                        @foreach ($sizes as $size)
                            <button type="button" class="variant-option"
                                    x-on:click="pickSize('{{ $size }}')"
                                    :aria-pressed="selectedSize === '{{ $size }}'"
                                    :disabled="!hasVariant('{{ $size }}', selectedColor)">
                                {{ $size }}
                            </button>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="mt-3 text-xs font-medium" :class="inStock ? 'text-green-700' : 'text-gray-400'"
                 x-text="inStock ? 'Còn ' + maxQty + ' sản phẩm' : 'Hết hàng với lựa chọn này'"></div>

            <div class="mt-6 flex items-center gap-4">
                <div class="inline-flex items-center rounded-xl border border-gray-200">
                    <button type="button" class="flex size-11 items-center justify-center text-gray-500 hover:text-brand" x-on:click="dec()" aria-label="Giảm số lượng">−</button>
                    <span class="w-10 text-center text-sm font-semibold" x-text="qty"></span>
                    <button type="button" class="flex size-11 items-center justify-center text-gray-500 hover:text-brand" x-on:click="inc()" aria-label="Tăng số lượng">+</button>
                </div>
                <button type="button" class="btn btn-primary flex-1" :disabled="!inStock"
                        x-on:click="$dispatch('notify', 'Giỏ hàng sẽ sớm ra mắt — cảm ơn bạn đã quan tâm!')">
                    <x-icon name="box" class="size-4" /> Thêm vào giỏ
                </button>
            </div>
            <p class="mt-3 flex items-center gap-1.5 text-xs text-gray-500"
               x-data="{ msg: '', timer: null }"
               x-on:notify.window="msg = $event.detail; clearTimeout(timer); timer = setTimeout(() => msg = '', 3500)"
               x-show="msg" x-transition x-text="msg" x-cloak></p>

            <dl class="mt-8 space-y-2 border-t border-gray-100 pt-6 text-sm">
                <div class="flex justify-between"><dt class="text-gray-500">Danh mục</dt><dd class="text-gray-900">{{ $product->category?->name ?? '—' }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">Thương hiệu</dt><dd class="text-gray-900">{{ $product->brand?->name ?? '—' }}</dd></div>
            </dl>
        </div>
    </div>

    @if ($related->isNotEmpty())
        <section class="mt-16" aria-labelledby="related-title">
            <h2 id="related-title" class="display-title mb-5 text-2xl">Cùng thương hiệu {{ $product->brand?->name }}</h2>
            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($related as $item)
                    @php($image = $item->images->first())
                    <a href="{{ route('products.show', $item) }}" class="product-card group">
                        <div class="product-card-media">
                            @if ($image)
                                <img src="{{ \Illuminate\Support\Facades\Storage::disk('s3')->url($image->path) }}" alt="{{ $item->name }}" loading="lazy">
                            @else
                                <x-icon name="shirt" class="size-10" />
                            @endif
                        </div>
                        <div class="product-card-body">
                            <p class="product-card-eyebrow">{{ $item->brand?->name }}</p>
                            <h3>{{ $item->name }}</h3>
                            <div class="product-card-price">
                                <span class="font-semibold text-brand">{{ number_format((float) $item->base_price, 0) }} ₫</span>
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>
        </section>
    @endif
@endsection
