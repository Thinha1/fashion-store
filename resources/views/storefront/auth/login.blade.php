@extends('layouts.app')

@section('title', 'Đăng nhập')

@section('content')
    <div class="mx-auto max-w-sm">
        <h1 class="mb-6 text-xl font-semibold">Đăng nhập</h1>

        <form method="POST" action="{{ route('login') }}" class="space-y-4">
            @csrf

            <div>
                <x-label for="email">Email</x-label>
                <x-input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus class="mt-1" />
                <x-input-error :messages="$errors->get('email')" />
            </div>

            <div>
                <x-label for="password">Mật khẩu</x-label>
                <x-input id="password" type="password" name="password" required class="mt-1" />
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
