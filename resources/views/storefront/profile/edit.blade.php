@extends('layouts.app')

@section('title', 'Tài khoản')

@section('content')
    <div class="mx-auto w-full max-w-md">
        <p class="eyebrow mb-3">Thông tin cá nhân</p>
        <h1 class="mb-2 text-2xl font-semibold">Hồ sơ của tôi</h1>
        <p class="mb-7 text-sm leading-6 text-gray-500">Cập nhật thông tin để cửa hàng có thể liên hệ với bạn.</p>

        <form method="POST" action="{{ route('profile.update') }}" class="space-y-4">
            @csrf
            @method('PUT')

            <div>
                <x-label for="name">Họ tên</x-label>
                <x-input id="name" name="name" value="{{ old('name', $user->name) }}" required class="mt-1" />
                <x-input-error :messages="$errors->get('name')" />
            </div>

            <div>
                <x-label for="email">Email</x-label>
                <x-input id="email" type="email" name="email" value="{{ old('email', $user->email) }}" required class="mt-1" />
                <x-input-error :messages="$errors->get('email')" />
            </div>

            <div>
                <x-label for="phone">Số điện thoại</x-label>
                <x-input id="phone" name="phone" type="tel" autocomplete="tel" value="{{ old('phone', $user->phone) }}" class="mt-1" />
                <x-input-error :messages="$errors->get('phone')" />
            </div>

            <x-button type="submit">Lưu thay đổi</x-button>
        </form>
    </div>
@endsection
