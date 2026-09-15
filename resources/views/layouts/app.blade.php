<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#175b60">
    <title>@yield('title', config('app.name', 'Fashion Store'))</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="flex min-h-screen flex-col bg-gray-50 text-gray-900 antialiased">
    <a href="#main-content" class="skip-link">Đến nội dung chính</a>
    <div class="bg-brand px-4 py-2 text-center text-xs tracking-wide text-white">Một chút cảm hứng. Một phong cách của
        riêng bạn.</div>
    <header class="relative border-b border-gray-200 bg-white" x-data="{ menuOpen: false, megaOpen: null }"
        x-on:keydown.escape.window="menuOpen = false; megaOpen = null" x-on:click.outside="megaOpen = null">
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-5 py-5 sm:px-8">
            <x-brand />
            <nav aria-label="Điều hướng chính" class="hidden items-center gap-1 md:flex">
                <a href="{{ route('home') }}" class="nav-link"
                    @if (request()->routeIs('home')) aria-current="page" @endif>Trang chủ</a>
                <a href="{{ route('products.index') }}" class="nav-link"
                    @if (request()->routeIs('products.*') && ! request('category')) aria-current="page" @endif>Sản phẩm</a>

                @foreach ($megaMenu as $topCategory)
                    <div>
                        <button type="button" class="mega-trigger"
                                x-on:click="megaOpen = megaOpen === '{{ $topCategory->slug }}' ? null : '{{ $topCategory->slug }}'"
                                :aria-expanded="megaOpen === '{{ $topCategory->slug }}'">
                            {{ $topCategory->name }}
                            <svg class="size-3 transition-transform" :class="megaOpen === '{{ $topCategory->slug }}' && 'rotate-180'" viewBox="0 0 12 12" fill="none" aria-hidden="true"><path d="M2.5 4.5L6 8l3.5-3.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" /></svg>
                        </button>
                        <div class="mega-panel" x-show="megaOpen === '{{ $topCategory->slug }}'" x-cloak
                             x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 -translate-y-1"
                             x-transition:enter-end="opacity-100 translate-y-0">
                            <div class="mega-panel-inner" style="grid-template-columns: repeat({{ max($topCategory->children->count(), 1) }}, minmax(0, 1fr));">
                                @foreach ($topCategory->children as $childCategory)
                                    <div class="mega-col">
                                        <h3><x-icon name="shirt" class="size-4 text-brand" /> {{ \Illuminate\Support\Str::upper($childCategory->name) }}</h3>
                                        <ul>
                                            @if ($childCategory->children->isNotEmpty())
                                                @foreach ($childCategory->children as $styleCategory)
                                                    <li><a href="{{ route('products.index', ['category' => $styleCategory->slug]) }}" x-on:click="megaOpen = null">{{ $styleCategory->name }}</a></li>
                                                @endforeach
                                            @else
                                                @forelse ($childCategory->products as $product)
                                                    <li><a href="{{ route('products.show', $product) }}" x-on:click="megaOpen = null">{{ $product->name }}</a></li>
                                                @empty
                                                    <li class="text-sm text-gray-300">Đang cập nhật</li>
                                                @endforelse
                                            @endif
                                        </ul>
                                        <a href="{{ route('products.index', ['category' => $childCategory->slug]) }}" class="mega-see-all" x-on:click="megaOpen = null">Xem tất cả &rarr;</a>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endforeach

                <a href="{{ route('collections.index') }}" class="nav-link"
                    @if (request()->routeIs('collections.*')) aria-current="page" @endif>Bộ sưu tập</a>
                <a href="{{ route('home') }}#phong-cach" class="nav-link">Gợi ý phong cách</a>
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
            <button type="button" x-on:click="menuOpen = !menuOpen" :aria-expanded="menuOpen"
                aria-controls="mobile-nav" aria-label="Mở menu" class="btn btn-secondary px-3 md:hidden"><x-icon
                    name="menu" /></button>
        </div>
        <nav id="mobile-nav" aria-label="Điều hướng trên điện thoại" x-cloak x-show="menuOpen"
            class="flex flex-col gap-2 border-t border-gray-100 p-5 md:hidden">
            <a href="{{ route('home') }}" class="nav-link">Trang chủ</a>
            <a href="{{ route('products.index') }}" class="nav-link">Sản phẩm</a>

            @foreach ($megaMenu as $topCategory)
                <div class="mt-1 border-t border-gray-100 pt-2">
                    <a href="{{ route('products.index', ['category' => $topCategory->slug]) }}" x-on:click="menuOpen = false"
                       class="nav-link block text-xs font-semibold tracking-[0.1em] text-gray-400 uppercase">{{ $topCategory->name }}</a>
                    @foreach ($topCategory->children as $childCategory)
                        <a href="{{ route('products.index', ['category' => $childCategory->slug]) }}" x-on:click="menuOpen = false"
                           class="nav-link pl-6">{{ $childCategory->name }}</a>
                        @foreach ($childCategory->children as $styleCategory)
                            <a href="{{ route('products.index', ['category' => $styleCategory->slug]) }}" x-on:click="menuOpen = false"
                               class="nav-link pl-10 text-xs text-gray-400">{{ $styleCategory->name }}</a>
                        @endforeach
                    @endforeach
                </div>
            @endforeach

            <a href="{{ route('collections.index') }}" x-on:click="menuOpen = false" class="nav-link mt-1 border-t border-gray-100 pt-3">Bộ sưu tập</a>
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
</body>

</html>
