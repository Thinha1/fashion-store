@extends('layouts.app')

@section('title', 'Đăng ký')

@section('content')
    <div class="mx-auto max-w-sm">
        <h1 class="mb-6 text-xl font-semibold">Đăng ký tài khoản</h1>

        <form method="POST" action="{{ route('register') }}" class="space-y-4">
            @csrf

            <div>
                <x-label for="name">Họ tên</x-label>
                <x-input id="name" name="name" value="{{ old('name') }}" required autofocus class="mt-1" />
                <x-input-error :messages="$errors->get('name')" />
            </div>

            <div>
                <x-label for="email">Email</x-label>
                <x-input id="email" type="email" name="email" value="{{ old('email') }}" required class="mt-1" />
                <x-input-error :messages="$errors->get('email')" />
            </div>

            <div>
                <x-label for="phone">Số điện thoại</x-label>
                <x-input id="phone" name="phone" value="{{ old('phone') }}" class="mt-1" />
                <x-input-error :messages="$errors->get('phone')" />
            </div>

            <div>
                <x-label for="password">Mật khẩu</x-label>
                <x-input id="password" type="password" name="password" required class="mt-1" />
                <x-input-error :messages="$errors->get('password')" />
            </div>

            <div>
                <x-label for="password_confirmation">Xác nhận mật khẩu</x-label>
                <x-input id="password_confirmation" type="password" name="password_confirmation" required class="mt-1" />
            </div>

            <x-button type="submit" class="w-full">Đăng ký</x-button>
        </form>

        <p class="mt-4 text-sm text-gray-600">
            Đã có tài khoản?
            <a href="{{ route('login') }}" class="font-medium text-gray-900 underline">Đăng nhập</a>
        </p>
    </div>
@endsection
