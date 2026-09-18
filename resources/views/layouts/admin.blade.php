<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#175b60">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Quản trị') - {{ config('app.name', 'Fashion Store') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="admin-shell min-h-screen bg-gray-50 text-gray-900 antialiased" x-data="{ sidebarOpen: false, desktop: window.innerWidth >= 1024 }" x-effect="document.body.style.overflow = sidebarOpen && !desktop ? 'hidden' : ''" x-on:resize.window="desktop = window.innerWidth >= 1024; if (desktop) sidebarOpen = false" x-on:keydown.escape.window="if (sidebarOpen) { sidebarOpen = false; $nextTick(() => $refs.menuButton.focus()); }">
    <a href="#main-content" class="skip-link">Đến nội dung chính</a>
    <div x-cloak x-show="sidebarOpen" class="fixed inset-0 z-30 bg-gray-900/40 lg:hidden" x-on:click="sidebarOpen = false; $nextTick(() => $refs.menuButton.focus())" aria-hidden="true"></div>
    <aside id="admin-navigation" class="admin-sidebar" data-open="false" :data-open="sidebarOpen ? 'true' : 'false'" x-bind:inert="!sidebarOpen && !desktop">
        <div class="flex items-center justify-between border-b border-white/10 px-5 py-5"><x-brand /><button type="button" x-ref="closeMenu" class="rounded-lg p-2 text-gray-500 lg:hidden" aria-label="Đóng menu" x-on:click="sidebarOpen = false; $nextTick(() => $refs.menuButton.focus())"><x-icon name="close" /></button></div>
        <nav aria-label="Điều hướng quản trị" tabindex="0" class="flex-1 space-y-6 overflow-y-auto px-3 py-5">
            <div><p class="sidebar-label">Điều hành</p><a href="{{ route('admin.dashboard') }}" class="sidebar-link" @if(request()->routeIs('admin.dashboard')) aria-current="page" @endif><x-icon name="grid" /> Tổng quan</a></div>
            @if (auth()->user()->hasPermission('products.manage'))
                <div><p class="sidebar-label">Sản phẩm & thương hiệu</p>
                    @foreach ([['products', 'shirt', 'Sản phẩm'], ['categories', 'layers', 'Danh mục'], ['brands', 'tag', 'Thương hiệu'], ['discounts', 'percent', 'Giảm giá']] as [$section, $icon, $label])
                        <a href="{{ route('admin.'.$section.'.index') }}" class="sidebar-link" @if(request()->routeIs('admin.'.$section.'.*')) aria-current="page" @endif><x-icon :name="$icon" /> {{ $label }}</a>
                    @endforeach
                </div>
            @endif
            @if (auth()->user()->hasPermission('suppliers.manage') || auth()->user()->hasPermission('inventory.manage'))
                <div><p class="sidebar-label">Kho & cung ứng</p>
                    @if (auth()->user()->hasPermission('suppliers.manage')) <a href="{{ route('admin.suppliers.index') }}" class="sidebar-link" @if(request()->routeIs('admin.suppliers.*')) aria-current="page" @endif><x-icon name="truck" /> Nhà cung cấp</a> @endif
                    @if (auth()->user()->hasPermission('inventory.manage')) <a href="{{ route('admin.goods-receipts.index') }}" class="sidebar-link" @if(request()->routeIs('admin.goods-receipts.*')) aria-current="page" @endif><x-icon name="box" /> Phiếu nhập hàng</a> @endif
                </div>
            @endif
            <div><p class="sidebar-label">Cửa hàng</p><a href="{{ route('home') }}" class="sidebar-link"><x-icon name="home" /> Xem trang cửa hàng <x-icon class="ml-auto size-4" /></a></div>
        </nav>
        <div class="mx-4 mb-4 flex items-center gap-3 border-t border-white/10 pt-4"><span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-brand-soft text-sm font-semibold text-brand">{{ mb_substr(auth()->user()->name, 0, 1) }}</span><div class="min-w-0"><p class="truncate text-sm font-semibold">{{ auth()->user()->name }}</p><p class="text-xs text-slate-300">Quản trị cửa hàng</p></div></div>
    </aside>
    <div class="admin-frame min-w-0" x-bind:inert="sidebarOpen && !desktop">
        <header class="sticky top-0 z-20 flex min-h-16 items-center justify-between gap-3 border-b border-gray-200 bg-white px-4  sm:px-8">
            <div class="flex min-w-0 items-center gap-3"><button type="button" x-ref="menuButton" x-on:click="sidebarOpen = true; $nextTick(() => $refs.closeMenu.focus())" :aria-expanded="sidebarOpen" aria-controls="admin-navigation" aria-label="Mở menu quản trị" class="btn btn-secondary px-3 lg:hidden"><x-icon name="menu" /></button><div class="flex min-w-0 items-center gap-2 text-sm"><span class="hidden text-gray-400 sm:inline">Quản trị</span><span class="hidden text-gray-300 sm:inline">/</span><span class="truncate font-medium">@yield('title', 'Tổng quan')</span></div></div>
            <form method="POST" action="{{ route('logout') }}">@csrf <x-button variant="secondary"><x-icon name="logout" class="size-4" /><span>Đăng xuất</span></x-button></form>
        </header>
        <main id="main-content" class="admin-content" tabindex="-1"><x-flash-messages />@yield('content')</main>
        <footer class="px-4 pb-6 text-xs text-gray-400 sm:px-8">&copy; {{ now()->year }} {{ config('app.name', 'Fashion Store') }} · Quản lý cửa hàng</footer>
    </div>
    <x-admin.confirm-dialog />
    @if (auth()->user()->hasPermission('products.manage'))
        <x-admin.ai-chat-widget />
    @endif
</body>
</html>
