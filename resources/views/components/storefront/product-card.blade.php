@props(['product', 'eyebrow' => null])
@php
    $image = $product->images->first();
    $outOfStock = $product->stock_total !== null && (int) $product->stock_total === 0;
    $colors = $product->relationLoaded('variants') ? $product->variants->pluck('color')->filter()->unique()->values() : collect();
@endphp
<a href="{{ route('products.show', $product) }}" class="pg-card group">
    <div class="pg-media">
        @if ($product->is_featured || $outOfStock)
            <div class="pg-tags">
                @if ($product->is_featured)
                    <span class="product-card-badge">Nổi bật</span>
                @endif
                @if ($outOfStock)
                    <span class="product-card-badge product-card-badge-muted">Hết hàng</span>
                @endif
            </div>
        @endif
        @if ($image)
            <img src="{{ \Illuminate\Support\Facades\Storage::disk(config('filesystems.image_disk'))->url($image->path) }}"
                 alt="{{ $image->alt_text ?? $product->name }}" loading="lazy">
        @else
            <x-icon name="shirt" class="size-11" />
        @endif
    </div>
    <div class="pg-body">
        <p class="pg-brand">{{ $eyebrow ?? $product->brand?->name ?? 'Fashion Store' }}</p>
        <h3>{{ $product->name }}</h3>
        <span class="pg-price">{{ number_format((float) $product->base_price, 0, ',', '.') }} ₫</span>
        @if ($colors->isNotEmpty())
            <div class="pg-dots">
                @foreach ($colors->take(3) as $color)
                    <i style="background-color: {{ \App\Support\ColorSwatches::hex($color) }}" title="{{ $color }}"></i>
                @endforeach
                <span>{{ $colors->count() }} màu</span>
            </div>
        @endif
    </div>
</a>
