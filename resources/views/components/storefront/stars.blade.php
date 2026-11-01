@props(['rating', 'label' => null])

{{-- Five stars, filled up to the rating (rounded to the nearest whole star). --}}
@php($filled = (int) round((float) $rating))
<span {{ $attributes->class(['stars']) }} role="img" aria-label="{{ $label ?? number_format((float) $rating, 1, ',', '.').' trên 5 sao' }}">
    @for ($i = 1; $i <= 5; $i++)
        <x-icon name="star" @class(['stars-on' => $i <= $filled]) />
    @endfor
</span>
