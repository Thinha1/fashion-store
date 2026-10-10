@extends('layouts.app')

@section('title', ($address->exists ? 'Sửa địa chỉ' : 'Thêm địa chỉ') . ' — ' . config('app.name', 'Fashion Store'))

@section('content')
    <div class="mx-auto w-full max-w-2xl">
        <a href="{{ route('addresses.index') }}" class="mb-5 inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-brand">
            <x-icon name="back" class="size-3.5" /> Sổ địa chỉ
        </a>
        <h1 class="display-title mb-7 text-3xl">{{ $address->exists ? 'Sửa địa chỉ' : 'Thêm địa chỉ mới' }}</h1>

        <form method="POST" action="{{ $address->exists ? route('addresses.update', $address) : route('addresses.store') }}"
              class="space-y-5 rounded-2xl border border-gray-200 bg-white p-6 sm:p-8">
            @csrf
            @if ($address->exists)
                @method('PUT')
            @endif

            @include('storefront.addresses._fields')

            @unless ($address->is_default)
                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input type="hidden" name="is_default" value="0">
                    <input type="checkbox" name="is_default" value="1" class="size-4 rounded border-gray-300 accent-brand" @checked(old('is_default'))>
                    Đặt làm địa chỉ mặc định
                </label>
            @endunless

            <div class="flex gap-3">
                <x-button type="submit"><x-icon name="save" class="size-4" /> Lưu địa chỉ</x-button>
                <a href="{{ route('addresses.index') }}" class="btn btn-secondary">Hủy</a>
            </div>
        </form>
    </div>
@endsection
