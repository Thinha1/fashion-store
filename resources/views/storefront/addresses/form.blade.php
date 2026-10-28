@extends('layouts.app')

@section('title', ($address->exists ? 'Sửa địa chỉ' : 'Thêm địa chỉ') . ' — ' . config('app.name', 'Fashion Store'))

@section('content')
    <div class="container page narrow">
        <nav aria-label="Breadcrumb" class="crumbs">
            <ol>
                <li><a href="{{ route('profile.edit') }}">Tài khoản</a></li>
                <li><a href="{{ route('addresses.index') }}">Sổ địa chỉ</a></li>
                <li aria-current="page">{{ $address->exists ? 'Sửa địa chỉ' : 'Thêm địa chỉ' }}</li>
            </ol>
        </nav>
        <h1 class="form-title">{{ $address->exists ? 'Sửa địa chỉ' : 'Thêm địa chỉ mới' }}</h1>

        <form method="POST" action="{{ $address->exists ? route('addresses.update', $address) : route('addresses.store') }}"
              class="form-panel">
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
