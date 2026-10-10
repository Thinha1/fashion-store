@props(['amount'])

{{-- Whole VND with Vietnamese grouping: 1.250.000 ₫ --}}
<span {{ $attributes }}>{{ number_format((float) $amount, 0, ',', '.') }} ₫</span>
