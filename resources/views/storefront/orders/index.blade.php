@extends('layouts.app')

@section('title', 'Đơn hàng của tôi — ' . config('app.name', 'Fashion Store'))

@section('content')
    <div class="mx-auto w-full max-w-4xl">
        <a href="{{ route('profile.edit') }}" class="mb-5 inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-brand">
            <x-icon name="back" class="size-3.5" /> Tài khoản
        </a>
        <div class="mb-7">
            <p class="eyebrow">Tài khoản</p>
            <h1 class="display-title mt-2 text-3xl">Đơn hàng của tôi</h1>
        </div>

        @if ($orders->isEmpty())
            <div class="flex flex-col items-center gap-4 rounded-2xl border border-dashed border-gray-300 bg-white px-6 py-16 text-center">
                <x-icon name="orders" class="size-8 text-gray-300" />
                <p class="text-sm text-gray-500">Bạn chưa có đơn hàng nào.</p>
                <a href="{{ route('products.index') }}" class="btn btn-primary">Bắt đầu mua sắm <x-icon class="size-4" /></a>
            </div>
        @else
            <ul class="space-y-4">
                @foreach ($orders as $order)
                    <li>
                        <a href="{{ route('orders.show', $order) }}" class="block rounded-2xl border border-gray-200 bg-white p-5 transition-colors hover:border-brand">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <p class="font-semibold text-gray-900">{{ $order->order_number }}</p>
                                    <p class="mt-0.5 text-xs text-gray-500">{{ $order->placed_at->format('H:i d/m/Y') }} · {{ $order->items_count }} sản phẩm</p>
                                </div>
                                <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $order->status === 'cancelled' ? 'bg-gray-100 text-gray-600' : 'bg-brand-soft text-brand' }}">
                                    {{ $order->statusLabel() }}
                                </span>
                            </div>
                            <div class="mt-3 flex flex-wrap items-end justify-between gap-3">
                                <p class="min-w-0 truncate text-sm text-gray-600">{{ $order->items->pluck('product_name')->join(', ') }}</p>
                                <x-money :amount="$order->grand_total" class="shrink-0 font-semibold text-gray-900" />
                            </div>
                        </a>
                    </li>
                @endforeach
            </ul>
            <div class="mt-8">{{ $orders->links() }}</div>
        @endif
    </div>
@endsection
