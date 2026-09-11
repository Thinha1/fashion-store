@extends('layouts.app')

@section('title', 'Tài khoản')

@section('content')
    <div class="mx-auto max-w-sm">
        <h1 class="mb-6 text-xl font-semibold">Hồ sơ của tôi</h1>

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
                <x-input id="phone" name="phone" value="{{ old('phone', $user->phone) }}" class="mt-1" />
                <x-input-error :messages="$errors->get('phone')" />
            </div>

            <x-button type="submit">Lưu thay đổi</x-button>
        </form>
    </div>
@endsection
