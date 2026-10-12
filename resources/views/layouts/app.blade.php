<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#175b60">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name', 'Fashion Store'))</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

@php($currentProduct = request()->route('product'))
<body class="flex min-h-screen flex-col bg-gray-50 text-gray-900 antialiased"
      data-current-product-id="{{ $currentProduct instanceof \App\Models\Product ? $currentProduct->id : '' }}">
    <a href="#main-content" class="skip-link">Đến nội dung chính</a>
    <div class="bg-brand px-4 py-2 text-center text-xs tracking-wide text-white">Một chút cảm hứng. Một phong cách của
        riêng bạn.</div>
    <header class="relative border-b border-gray-200 bg-white" x-data="{ menuOpen: false }"
        x-on:keydown.escape.window="menuOpen = false">
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-5 py-5 sm:px-8">
            <x-brand />
            <nav aria-label="Điều hướng chính" class="hidden items-center gap-1 md:flex">
                <a href="{{ route('home') }}" class="nav-link"
                    @if (request()->routeIs('home')) aria-current="page" @endif>Trang chủ</a>
                <a href="{{ route('products.index') }}" class="nav-link"
                    @if (request()->routeIs('products.*')) aria-current="page" @endif>Sản phẩm</a>
                <a href="{{ route('collections.index') }}" class="nav-link"
                    @if (request()->routeIs('collections.*')) aria-current="page" @endif>Bộ sưu tập</a>
                <a href="{{ route('home') }}#phong-cach" class="nav-link">Gợi ý phong cách</a>
                <a href="{{ route('cart.index') }}" class="nav-link relative" aria-label="Giỏ hàng ({{ $cartItemCount }} sản phẩm)"
                    @if (request()->routeIs('cart.*')) aria-current="page" @endif>
                    <x-icon name="cart" class="size-4" />
                    @if ($cartItemCount > 0)
                        <span class="absolute -top-0.5 -right-0.5 flex h-4.5 min-w-4.5 items-center justify-center rounded-full bg-brand px-1 text-[0.65rem] font-semibold text-white">{{ $cartItemCount > 99 ? '99+' : $cartItemCount }}</span>
                    @endif
                </a>
                @auth
                    @if (auth()->user()->hasPermission('admin.access'))
                        <a href="{{ route('admin.dashboard') }}" class="nav-link">Quản trị</a>
                    @endif
                    <a href="{{ route('profile.edit') }}" class="btn btn-secondary"><x-icon name="user" /> Tài khoản</a>
                    <form method="POST" action="{{ route('logout') }}">@csrf <x-button variant="secondary"
                            aria-label="Đăng xuất"><x-icon name="logout" /></x-button></form>
                @else
                    <a href="{{ route('login') }}" class="nav-link"
                        @if (request()->routeIs('login')) aria-current="page" @endif>Đăng nhập</a>
                    <a href="{{ route('register') }}" class="btn btn-primary">Tạo tài khoản <x-icon class="size-4" /></a>
                @endauth
            </nav>
            <div class="flex items-center gap-2 md:hidden">
                <a href="{{ route('cart.index') }}" class="btn btn-secondary relative px-3" aria-label="Giỏ hàng ({{ $cartItemCount }} sản phẩm)">
                    <x-icon name="cart" />
                    @if ($cartItemCount > 0)
                        <span class="absolute -top-1.5 -right-1.5 flex h-4.5 min-w-4.5 items-center justify-center rounded-full bg-brand px-1 text-[0.65rem] font-semibold text-white">{{ $cartItemCount > 99 ? '99+' : $cartItemCount }}</span>
                    @endif
                </a>
                <button type="button" x-on:click="menuOpen = !menuOpen" :aria-expanded="menuOpen"
                    aria-controls="mobile-nav" aria-label="Mở menu" class="btn btn-secondary px-3"><x-icon
                        name="menu" /></button>
            </div>
        </div>
        <nav id="mobile-nav" aria-label="Điều hướng trên điện thoại" x-cloak x-show="menuOpen"
            class="flex flex-col gap-2 border-t border-gray-100 p-5 md:hidden">
            <a href="{{ route('home') }}" class="nav-link">Trang chủ</a>
            <a href="{{ route('products.index') }}" class="nav-link">Sản phẩm</a>
            <a href="{{ route('collections.index') }}" x-on:click="menuOpen = false" class="nav-link">Bộ sưu tập</a>
            <a href="{{ route('home') }}#phong-cach" x-on:click="menuOpen = false" class="nav-link">Gợi ý phong cách</a>
            @auth
                @if (auth()->user()->hasPermission('admin.access'))
                    <a href="{{ route('admin.dashboard') }}" class="nav-link">Quản trị</a>
                @endif
                <a href="{{ route('profile.edit') }}" class="nav-link">Tài khoản</a>
                <form method="POST" action="{{ route('logout') }}">@csrf <x-button variant="secondary" class="w-full">Đăng
                        xuất</x-button></form>
            @else
                <a href="{{ route('login') }}" class="btn btn-secondary">Đăng nhập</a>
                <a href="{{ route('register') }}" class="btn btn-primary">Tạo tài khoản</a>
            @endauth
        </nav>
    </header>
    <main id="main-content" class="store-main" tabindex="-1">
        <x-flash-messages />
        @if (request()->routeIs('login', 'register', 'password.*', 'verification.notice', 'profile.edit'))
            <div class="auth-shell">
                <aside class="auth-story">
                    <div>
                        <p class="text-xs tracking-[0.2em] uppercase text-white/70">Phong cách mỗi ngày</p>
                        <h2 class="display-title mt-5 text-4xl leading-tight">Tự tin hơn.<br>Đúng chất bạn.</h2>
                        <p class="mt-5 max-w-xs text-sm leading-7 text-white/75">Một không gian dành cho phong cách và
                            những lựa chọn của riêng bạn.</p>
                    </div>
                    <x-outfit-art look="daily" class="mx-auto my-4 w-full max-w-64" />
                    <a href="{{ route('home') }}"
                        class="flex items-center gap-2 text-sm text-white/80 hover:text-white"><x-icon name="home"
                            class="size-4" /> Trở về trang chủ</a>
                </aside>
                <div class="auth-card">@yield('content')</div>
            </div>
        @else
            @yield('content')
        @endif
    </main>
    <footer class="border-t border-gray-200 bg-white">
        <div
            class="mx-auto flex max-w-7xl flex-col justify-between gap-5 px-5 py-8 sm:flex-row sm:items-center sm:px-8">
            <div>
                <p class="font-semibold">{{ config('app.name', 'Fashion Store') }}</p>
                <p class="mt-1 text-sm text-gray-500">Phong cách bắt đầu từ chính bạn.</p>
            </div>
            <div class="flex flex-wrap gap-5 text-sm text-gray-500"><a href="{{ route('home') }}#phong-cach"
                    class="hover:text-brand">Gợi ý phong cách</a><a href="{{ route('login') }}"
                    class="hover:text-brand">Tài khoản</a><span>&copy; {{ now()->year }}
                    {{ config('app.name', 'Fashion Store') }}</span></div>
        </div>
    </footer>
    <x-storefront.shopping-assist-widget />
</body>

</html>
