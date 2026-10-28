<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#1a1a22">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="@yield('description', 'Áo, quần và váy từ Coolmate, Yame, Nike, Puma, Uniqlo và Teelab, giao tận nhà.')">
    <script>document.documentElement.classList.add('js');</script>
    <title>@yield('title', config('app.name', 'Fashion Store'))</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wdth,wght@12..96,75..100,500..800&family=Source+Serif+4:ital,opsz,wght@0,8..60,400;1,8..60,400&display=swap&subset=vietnamese">
    @vite(['resources/css/store.css', 'resources/js/app.js'])
    @stack('head')
</head>

@php($currentProduct = request()->route('product'))
<body class="flex min-h-screen flex-col"
      data-current-product-id="{{ $currentProduct instanceof \App\Models\Product ? $currentProduct->id : '' }}">
    <a href="#main-content" class="skip">Đến nội dung chính</a>
    <header class="slab" x-data="{ menuOpen: false }" x-on:keydown.escape.window="menuOpen = false">
        <div class="container slab-row">
            <x-storefront.wordmark />
            <nav aria-label="Điều hướng chính" class="slab-links">
                <a href="{{ route('home') }}" class="slab-link"
                    @if (request()->routeIs('home')) aria-current="page" @endif>Trang chủ</a>
                <a href="{{ route('products.index') }}" class="slab-link"
                    @if (request()->routeIs('products.*')) aria-current="page" @endif>Sản phẩm</a>
                <a href="{{ route('collections.index') }}" class="slab-link"
                    @if (request()->routeIs('collections.*')) aria-current="page" @endif>Bộ sưu tập</a>
                <a href="{{ route('home') }}#phong-cach" class="slab-link">Gợi ý phong cách</a>
            </nav>
            <div class="slab-tools">
                <a href="{{ route('products.index') }}#tim-kiem" class="slab-icon" aria-label="Tìm kiếm sản phẩm" title="Tìm kiếm">
                    <x-icon name="search" />
                </a>
                <a href="{{ route('cart.index') }}" class="slab-icon" aria-label="Giỏ hàng ({{ $cartItemCount }} sản phẩm)"
                    @if (request()->routeIs('cart.*')) aria-current="page" @endif>
                    <x-icon name="cart" />
                    @if ($cartItemCount > 0)
                        <span class="slab-count" aria-hidden="true">{{ $cartItemCount > 99 ? '99+' : $cartItemCount }}</span>
                    @endif
                </a>
                @auth
                    @if (auth()->user()->hasPermission('admin.access'))
                        <a href="{{ route('admin.dashboard') }}" class="slab-link slab-hide-sm">Quản trị</a>
                    @endif
                    <a href="{{ route('profile.edit') }}" class="slab-cta slab-cta--quiet slab-hide-sm"
                        @if (request()->routeIs('profile.*', 'addresses.*', 'orders.*')) aria-current="page" @endif><x-icon name="user" /> Tài khoản</a>
                    <form method="POST" action="{{ route('logout') }}" class="slab-hide-sm">@csrf
                        <button type="submit" class="slab-icon" aria-label="Đăng xuất" title="Đăng xuất"><x-icon name="logout" /></button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="slab-link slab-hide-sm"
                        @if (request()->routeIs('login')) aria-current="page" @endif>Đăng nhập</a>
                    <a href="{{ route('register') }}" class="slab-cta slab-hide-sm">Tạo tài khoản</a>
                @endauth
                <button type="button" x-on:click="menuOpen = !menuOpen" :aria-expanded="menuOpen.toString()" aria-expanded="false"
                    aria-controls="mobile-nav" aria-label="Mở menu" class="slab-icon slab-menu"><x-icon name="menu" x-show="!menuOpen" /><x-icon name="close" x-show="menuOpen" x-cloak /></button>
            </div>
        </div>
        <nav id="mobile-nav" aria-label="Điều hướng trên điện thoại" x-cloak x-show="menuOpen" class="container slab-sheet">
            <a href="{{ route('home') }}" class="slab-link">Trang chủ</a>
            <a href="{{ route('products.index') }}" class="slab-link">Sản phẩm</a>
            <a href="{{ route('collections.index') }}" x-on:click="menuOpen = false" class="slab-link">Bộ sưu tập</a>
            <a href="{{ route('home') }}#phong-cach" x-on:click="menuOpen = false" class="slab-link">Gợi ý phong cách</a>
            @auth
                @if (auth()->user()->hasPermission('admin.access'))
                    <a href="{{ route('admin.dashboard') }}" class="slab-link">Quản trị</a>
                @endif
                <a href="{{ route('profile.edit') }}" class="slab-link">Tài khoản</a>
                <form method="POST" action="{{ route('logout') }}" class="slab-sheet-actions">@csrf
                    <button type="submit" class="slab-cta slab-cta--quiet">Đăng xuất</button>
                </form>
            @else
                <div class="slab-sheet-actions">
                    <a href="{{ route('login') }}" class="slab-cta slab-cta--quiet">Đăng nhập</a>
                    <a href="{{ route('register') }}" class="slab-cta">Tạo tài khoản</a>
                </div>
            @endauth
        </nav>
    </header>
    <main id="main-content" class="store-main" tabindex="-1">
        @if (session('status') || session('error'))
            <div class="container flash"><x-flash-messages /></div>
        @endif
        @if (request()->routeIs('login', 'register', 'password.*', 'verification.notice', 'profile.edit'))
            <div class="container page">
                <div class="auth-shell">
                    <aside class="auth-story">
                        <h2>Tự tin hơn.<br>Đúng chất bạn.</h2>
                        <p>Một không gian dành cho phong cách và những lựa chọn của riêng bạn.</p>
                        <a href="{{ route('home') }}" class="auth-home"><x-icon name="home" /> Trở về trang chủ</a>
                    </aside>
                    <div class="auth-card">@yield('content')</div>
                </div>
            </div>
        @else
            @yield('content')
        @endif
    </main>
    <footer class="foot">
        <div class="container">
            <p class="foot-statement">Phong cách bắt đầu từ chính bạn.</p>
            <div class="foot-row">
                <nav aria-label="Liên kết cuối trang" class="foot-links">
                    <a href="{{ route('products.index') }}">Sản phẩm</a>
                    <a href="{{ route('collections.index') }}">Bộ sưu tập</a>
                    <a href="{{ route('home') }}#phong-cach">Gợi ý phong cách</a>
                    <a href="{{ route('login') }}">Tài khoản</a>
                </nav>
                <small>Everyday, your way. &copy; {{ now()->year }} {{ config('app.name', 'Fashion Store') }}</small>
            </div>
        </div>
    </footer>
    <x-storefront.shopping-assist-widget />
</body>

</html>
