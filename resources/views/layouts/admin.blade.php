<!DOCTYPE html>
<html lang="vi">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>@yield('title', 'Quản trị') - {{ config('app.name', 'Fashion Store') }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-gray-50 text-gray-900 antialiased">
        <div class="flex min-h-screen">
            <aside class="w-56 shrink-0 border-r border-gray-200 bg-white">
                <div class="px-4 py-4 text-lg font-semibold">{{ config('app.name', 'Fashion Store') }}</div>

                <nav class="flex flex-col gap-1 px-2 text-sm">
                    <a href="{{ route('admin.dashboard') }}" class="rounded-md px-3 py-2 text-gray-700 hover:bg-gray-100">Tổng quan</a>
                    @if (auth()->user()->hasPermission('products.manage'))
                        <a href="{{ route('admin.brands.index') }}" class="rounded-md px-3 py-2 text-gray-700 hover:bg-gray-100">Thương hiệu</a>
                        <a href="{{ route('admin.categories.index') }}" class="rounded-md px-3 py-2 text-gray-700 hover:bg-gray-100">Danh mục</a>
                        <a href="{{ route('admin.products.index') }}" class="rounded-md px-3 py-2 text-gray-700 hover:bg-gray-100">Sản phẩm</a>
                        <a href="{{ route('admin.discounts.index') }}" class="rounded-md px-3 py-2 text-gray-700 hover:bg-gray-100">Giảm giá</a>
                    @endif
                    @if (auth()->user()->hasPermission('suppliers.manage'))
                        <a href="{{ route('admin.suppliers.index') }}" class="rounded-md px-3 py-2 text-gray-700 hover:bg-gray-100">Nhà cung cấp</a>
                    @endif
                    @if (auth()->user()->hasPermission('inventory.manage'))
                        <a href="{{ route('admin.goods-receipts.index') }}" class="rounded-md px-3 py-2 text-gray-700 hover:bg-gray-100">Nhập hàng</a>
                    @endif
                </nav>
            </aside>

            <div class="flex-1">
                <header class="flex items-center justify-between border-b border-gray-200 bg-white px-6 py-4">
                    <h1 class="text-base font-semibold">@yield('title', 'Quản trị')</h1>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <x-button type="submit" variant="secondary">Đăng xuất</x-button>
                    </form>
                </header>

                <main class="px-6 py-6">
                    <x-flash-messages />

                    @yield('content')
                </main>
            </div>
        </div>
    </body>
</html>
