@extends('layouts.app')

@section('title', 'Đặt lại mật khẩu')

@section('content')
    <div class="mx-auto w-full max-w-md">
        <h1 class="mb-6 text-xl font-semibold">Đặt lại mật khẩu</h1>

        <form method="POST" action="{{ route('password.store') }}" class="space-y-4">
            @csrf

            <input type="hidden" name="token" value="{{ $token }}">

            <div>
                <x-label for="email">Email</x-label>
                <x-input id="email" type="email" name="email" autocomplete="email" placeholder="ban@example.com" value="{{ old('email', $email) }}" required autofocus class="mt-1" />
                <x-input-error :messages="$errors->get('email')" />
            </div>

            <div>
                <x-label for="password">Mật khẩu mới</x-label>
                <x-password-input id="password" name="password" autocomplete="new-password" />
                <x-input-error :messages="$errors->get('password')" />
            </div>

            <div>
                <x-label for="password_confirmation">Xác nhận mật khẩu mới</x-label>
                <x-password-input id="password_confirmation" name="password_confirmation" autocomplete="new-password" />
            </div>

            <x-button type="submit" class="w-full">Đặt lại mật khẩu</x-button>
        </form>
    </div>
@endsection
