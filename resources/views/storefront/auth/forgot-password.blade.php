@extends('layouts.app')

@section('title', 'Quên mật khẩu')

@section('content')
    <div class="mx-auto max-w-sm">
        <h1 class="mb-2 text-xl font-semibold">Quên mật khẩu</h1>
        <p class="mb-6 text-sm text-gray-600">Nhập email đã đăng ký, chúng tôi sẽ gửi liên kết đặt lại mật khẩu.</p>

        <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
            @csrf

            <div>
                <x-label for="email">Email</x-label>
                <x-input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus class="mt-1" />
                <x-input-error :messages="$errors->get('email')" />
            </div>

            <x-button type="submit" class="w-full">Gửi liên kết đặt lại mật khẩu</x-button>
        </form>
    </div>
@endsection
