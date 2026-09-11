<!DOCTYPE html>
<html lang="vi">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>@yield('title', config('app.name', 'Fashion Store'))</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-white text-gray-900 antialiased">
        <header class="border-b border-gray-200">
            <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-4">
                <a href="{{ url('/') }}" class="text-lg font-semibold">{{ config('app.name', 'Fashion Store') }}</a>

                <nav class="flex items-center gap-4 text-sm">
                    @auth
                        @if (auth()->user()->hasPermission('admin.access'))
                            <a href="{{ route('admin.dashboard') }}" class="text-gray-600 hover:text-gray-900">Quản trị</a>
                        @endif

                        <a href="{{ route('profile.edit') }}" class="text-gray-600 hover:text-gray-900">Tài khoản</a>

                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <x-button type="submit" variant="secondary">Đăng xuất</x-button>
                        </form>
                    @else
                        <a href="{{ route('login') }}" class="text-gray-600 hover:text-gray-900">Đăng nhập</a>
                        <a href="{{ route('register') }}" class="text-gray-600 hover:text-gray-900">Đăng ký</a>
                    @endauth
                </nav>
            </div>
        </header>

        <main class="mx-auto max-w-6xl px-4 py-8">
            <x-flash-messages />

            @yield('content')
        </main>

        <footer class="border-t border-gray-200 py-6 text-center text-sm text-gray-500">
            &copy; {{ now()->year }} {{ config('app.name', 'Fashion Store') }}
        </footer>
    </body>
</html>
