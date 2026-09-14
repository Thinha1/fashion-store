@extends('layouts.app')

@section('title', 'Đăng nhập')

@section('content')
    <div class="mx-auto w-full max-w-md">
        <p class="eyebrow mb-3">Chào mừng bạn trở lại</p>
        <h1 class="mb-2 text-2xl font-semibold">Đăng nhập</h1>
        <p class="mb-7 text-sm leading-6 text-gray-500">Đăng nhập để tiếp tục với tài khoản của bạn.</p>

        <form method="POST" action="{{ route('login') }}" class="space-y-4">
            @csrf

            <div>
                <x-label for="email">Email</x-label>
                <x-input id="email" type="email" name="email" autocomplete="email" placeholder="ban@example.com" value="{{ old('email') }}" required autofocus class="mt-1" />
                <x-input-error :messages="$errors->get('email')" />
            </div>

            <div>
                <x-label for="password">Mật khẩu</x-label>
                <x-password-input id="password" name="password" autocomplete="current-password" />
                <x-input-error :messages="$errors->get('password')" />
            </div>

            <div class="flex items-center justify-between text-sm">
                <label class="flex items-center gap-2">
                    <input type="checkbox" name="remember" class="rounded border-gray-300">
                    Ghi nhớ đăng nhập
                </label>

                <a href="{{ route('password.request') }}" class="text-gray-600 underline">Quên mật khẩu?</a>
            </div>

            <x-button type="submit" class="w-full">Đăng nhập</x-button>
        </form>

        <p class="mt-4 text-sm text-gray-600">
            Chưa có tài khoản?
            <a href="{{ route('register') }}" class="font-medium text-gray-900 underline">Đăng ký</a>
        </p>
    </div>
@endsection
