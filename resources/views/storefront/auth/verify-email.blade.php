@extends('layouts.app')

@section('title', 'Xác minh email')

@section('content')
    <div class="mx-auto max-w-sm">
        <h1 class="mb-2 text-xl font-semibold">Xác minh địa chỉ email</h1>
        <p class="mb-6 text-sm text-gray-600">
            Cảm ơn bạn đã đăng ký! Vui lòng kiểm tra hộp thư email để xác minh tài khoản trước khi tiếp tục.
        </p>

        @if (session('status') === 'verification-link-sent')
            <x-alert type="success" class="mb-4">
                Một liên kết xác minh mới đã được gửi tới email bạn đã đăng ký.
            </x-alert>
        @endif

        <div class="flex items-center gap-4">
            <form method="POST" action="{{ route('verification.send') }}">
                @csrf
                <x-button type="submit">Gửi lại email xác minh</x-button>
            </form>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <x-button type="submit" variant="secondary">Đăng xuất</x-button>
            </form>
        </div>
    </div>
@endsection
