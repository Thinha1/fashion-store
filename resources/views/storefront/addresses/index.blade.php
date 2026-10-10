@extends('layouts.app')

@section('title', 'Sổ địa chỉ — ' . config('app.name', 'Fashion Store'))

@section('content')
    <div class="mx-auto w-full max-w-3xl">
        <a href="{{ route('profile.edit') }}" class="mb-5 inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-brand">
            <x-icon name="back" class="size-3.5" /> Tài khoản
        </a>
        <div class="mb-7 flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="eyebrow">Tài khoản</p>
                <h1 class="display-title mt-2 text-3xl">Sổ địa chỉ</h1>
            </div>
            <a href="{{ route('addresses.create') }}" class="btn btn-primary"><x-icon name="plus" class="size-4" /> Thêm địa chỉ</a>
        </div>

        @if ($addresses->isEmpty())
            <div class="flex flex-col items-center gap-3 rounded-2xl border border-dashed border-gray-300 bg-white px-6 py-16 text-center">
                <x-icon name="home" class="size-8 text-gray-300" />
                <p class="text-sm text-gray-500">Bạn chưa lưu địa chỉ nào. Thêm một địa chỉ để thanh toán nhanh hơn.</p>
            </div>
        @else
            <ul class="space-y-4">
                @foreach ($addresses as $address)
                    <li class="rounded-2xl border bg-white p-5 {{ $address->is_default ? 'border-brand' : 'border-gray-200' }}">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="flex flex-wrap items-center gap-2 font-semibold text-gray-900">
                                    {{ $address->recipient_name }}
                                    <span class="font-normal text-gray-300">|</span>
                                    <span class="font-normal text-gray-600">{{ $address->phone }}</span>
                                    @if ($address->label)
                                        <span class="rounded-md bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">{{ $address->label }}</span>
                                    @endif
                                    @if ($address->is_default)
                                        <span class="rounded-md bg-brand-soft px-2 py-0.5 text-xs font-semibold text-brand">Mặc định</span>
                                    @endif
                                </p>
                                <p class="mt-1.5 text-sm leading-6 text-gray-600">{{ $address->fullAddress() }}</p>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                @unless ($address->is_default)
                                    <form method="POST" action="{{ route('addresses.default', $address) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="px-2 text-sm font-medium text-brand hover:underline">Đặt mặc định</button>
                                    </form>
                                @endunless
                                <a href="{{ route('addresses.edit', $address) }}" class="btn btn-secondary min-h-9 px-3 py-1.5" aria-label="Sửa địa chỉ">
                                    <x-icon name="edit" class="size-4" />
                                </a>
                                <form method="POST" action="{{ route('addresses.destroy', $address) }}"
                                      x-data x-on:submit="if (! confirm('Xóa địa chỉ này?')) $event.preventDefault()">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-secondary min-h-9 px-3 py-1.5 hover:border-red-300 hover:bg-red-50 hover:text-red-600" aria-label="Xóa địa chỉ">
                                        <x-icon name="delete" class="size-4" />
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
