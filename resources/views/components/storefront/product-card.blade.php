@props(['product', 'badge' => null, 'eager' => false, 'kicker' => null])

{{-- One product as the storefront lists it: the photograph at 4:5, the
     brand (or the kicker given, e.g. the category on a brand page), the
     name and the price. Expects brand, the primary image and
     listing prices eager-loaded. --}}
@php($image = $product->images->first())
<a href="{{ route('products.show', $product) }}" {{ $attributes->class(['pcard']) }}>
    <div class="pcard-media" data-slot="product-{{ $product->id }}" data-kind="product" data-alt="{{ $product->name }}">
        @if ($badge)
            <span class="tag tag--ink" data-keep>{{ $badge }}</span>
        @endif
        @if ($image)
            <img src="{{ \Illuminate\Support\Facades\Storage::disk(config('filesystems.image_disk'))->url($image->path) }}"
                 alt="{{ $image->alt_text ?: $product->name }}" width="800" height="1000"
                 @if ($eager) fetchpriority="high" @else loading="lazy" @endif>
        @endif
    </div>
    <div class="pcard-body">
        <span class="pcard-brand">{{ $kicker ?? $product->brand?->name ?? config('app.name', 'Fashion Store') }}</span>
        <span class="pcard-name" title="{{ $product->name }}">{{ \Illuminate\Support\Str::limit($product->name, 48, '…') }}</span>
        <x-storefront.price-tag :product="$product" />
    </div>
</a>
