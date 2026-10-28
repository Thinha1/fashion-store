@extends('layouts.app')

@section('title', 'Sổ địa chỉ — ' . config('app.name', 'Fashion Store'))

@section('content')
    <div class="container page narrow">
        <nav aria-label="Breadcrumb" class="crumbs">
            <ol>
                <li><a href="{{ route('profile.edit') }}">Tài khoản</a></li>
                <li aria-current="page">Sổ địa chỉ</li>
            </ol>
        </nav>
        <div class="catalog-head">
            <div class="catalog-title">
                <h1>Sổ địa chỉ</h1>
            </div>
            <a href="{{ route('addresses.create') }}" class="btn btn-primary"><x-icon name="plus" /> Thêm địa chỉ</a>
        </div>

        @if ($addresses->isEmpty())
            <div class="catalog-empty">
                <h2>Bạn chưa lưu địa chỉ nào.</h2>
                <p>Thêm một địa chỉ để thanh toán nhanh hơn.</p>
            </div>
        @else
            <ul class="address-list" role="list">
                @foreach ($addresses as $address)
                    <li class="address-item @if ($address->is_default) is-default @endif">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="flex flex-wrap items-center gap-2 font-semibold text-gray-900">
                                    {{ $address->recipient_name }}
                                    <span class="caption">{{ $address->phone }}</span>
                                    @if ($address->label)
                                        <span class="status is-muted">{{ $address->label }}</span>
                                    @endif
                                    @if ($address->is_default)
                                        <span class="tag tag--ink">Mặc định</span>
                                    @endif
                                </p>
                                <p class="mt-1.5 text-sm leading-6 text-gray-600">{{ $address->fullAddress() }}</p>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                @unless ($address->is_default)
                                    <form method="POST" action="{{ route('addresses.default', $address) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="text-link">Đặt mặc định</button>
                                    </form>
                                @endunless
                                <a href="{{ route('addresses.edit', $address) }}" class="btn btn-secondary" aria-label="Sửa địa chỉ">
                                    <x-icon name="edit" />
                                </a>
                                <form method="POST" action="{{ route('addresses.destroy', $address) }}"
                                      x-data x-on:submit="if (! confirm('Xóa địa chỉ này?')) $event.preventDefault()">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-secondary btn-danger" aria-label="Xóa địa chỉ">
                                        <x-icon name="delete" />
                                    </button>
                                </form>
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
@endsection
