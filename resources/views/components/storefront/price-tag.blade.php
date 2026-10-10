<span {{ $attributes->class(['inline-flex flex-wrap items-baseline gap-x-1.5']) }}>
    <x-money :amount="$tag->price" />
    @if ($tag->isDiscounted())
        <s class="text-xs font-normal text-gray-400"><x-money :amount="$tag->originalPrice" /></s>
    @endif
    @if ($tag->maxDiscountPercent > 0)
        <span class="rounded bg-red-50 px-1 text-[0.65rem] font-semibold text-red-600">-{{ $tag->maxDiscountPercent }}%</span>
    @endif
</span>
