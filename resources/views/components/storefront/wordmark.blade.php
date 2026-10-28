{{-- The storefront wordmark: the shop's name in the condensed display face,
     closed by a square of the accent green; the storefront nav and the
     admin sidebar both use it. --}}
<a href="{{ route('home') }}" {{ $attributes->merge(['class' => 'wordmark']) }} aria-label="{{ config('app.name', 'Fashion Store') }}, về trang chủ">
    <span>{{ config('app.name', 'Fashion Store') }}</span><span class="wordmark-dot" aria-hidden="true"></span>
</a>
