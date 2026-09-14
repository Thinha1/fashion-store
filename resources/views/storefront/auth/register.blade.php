@extends('layouts.app')

@section('title', 'Đăng ký')

@section('content')
    <div class="mx-auto w-full max-w-md">
        <p class="eyebrow mb-3">Bắt đầu tại đây</p>
        <h1 class="mb-2 text-2xl font-semibold">Đăng ký tài khoản</h1>
        <p class="mb-7 text-sm leading-6 text-gray-500">Điền thông tin bên dưới để tạo tài khoản của bạn.</p>

        <form method="POST" action="{{ route('register') }}" class="space-y-4">
            @csrf

            <div>
                <x-label for="name">Họ tên</x-label>
                <x-input id="name" name="name" autocomplete="name" placeholder="Nguyễn Minh Anh" value="{{ old('name') }}" required autofocus class="mt-1" />
                <x-input-error :messages="$errors->get('name')" />
            </div>

            <div>
                <x-label for="email">Email</x-label>
                <x-input id="email" type="email" name="email" autocomplete="email" placeholder="ban@example.com" value="{{ old('email') }}" required class="mt-1" />
                <x-input-error :messages="$errors->get('email')" />
            </div>

            <div>
                <x-label for="phone">Số điện thoại</x-label>
                <x-input id="phone" name="phone" type="tel" autocomplete="tel" value="{{ old('phone') }}" class="mt-1" />
                <x-input-error :messages="$errors->get('phone')" />
            </div>

            <div>
                <x-label for="password">Mật khẩu</x-label>
                <x-password-input id="password" name="password" autocomplete="new-password" />
                <p class="mt-2 text-xs text-gray-500">Sử dụng ít nhất 8 ký tự.</p>
                <x-input-error :messages="$errors->get('password')" />
            </div>

            <div>
                <x-label for="password_confirmation">Xác nhận mật khẩu</x-label>
                <x-password-input id="password_confirmation" name="password_confirmation" autocomplete="new-password" />
            </div>

            <x-button type="submit" class="w-full">Đăng ký</x-button>
        </form>

        <p class="mt-4 text-sm text-gray-600">
            Đã có tài khoản?
            <a href="{{ route('login') }}" class="font-medium text-gray-900 underline">Đăng nhập</a>
        </p>
    </div>
@endsection
