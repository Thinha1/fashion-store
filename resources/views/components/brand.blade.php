<a href="{{ route('home') }}" {{ $attributes->merge(['class' => 'brand-mark']) }} aria-label="{{ config('app.name', 'Fashion Store') }} — Trang chủ">
    <span class="brand-symbol"><x-icon name="shirt" class="size-6" /></span>
    <span>{{ config('app.name', 'Fashion Store') }}<span class="mt-0.5 block text-[9px] font-medium tracking-[0.22em] whitespace-nowrap uppercase text-gray-600">Everyday, your way</span></span>
</a>
