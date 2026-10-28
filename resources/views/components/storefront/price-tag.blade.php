<span {{ $attributes->class(['price']) }}>
    <x-money :amount="$tag->price" class="price-now" />
    @if ($tag->isDiscounted())
        <s class="price-was"><x-money :amount="$tag->originalPrice" /></s>
    @endif
    @if ($tag->maxDiscountPercent > 0)
        <span class="tag">-{{ $tag->maxDiscountPercent }}%</span>
    @endif
</span>
