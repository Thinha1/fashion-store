@extends('layouts.app')

@section('title', 'Đơn hàng ' . $order->order_number . ' — ' . config('app.name', 'Fashion Store'))

@section('content')
    <div class="mx-auto w-full max-w-4xl">
        <a href="{{ route('orders.index') }}" class="mb-5 inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-brand">
            <x-icon name="back" class="size-3.5" /> Đơn hàng của tôi
        </a>
        <div class="mb-8 flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="eyebrow">Đơn hàng</p>
                <h1 class="display-title mt-2 text-3xl">{{ $order->order_number }}</h1>
                <p class="mt-1 text-sm text-gray-500">Đặt lúc {{ $order->placed_at->format('H:i d/m/Y') }}</p>
            </div>
            <span class="rounded-full px-3 py-1 text-sm font-semibold {{ $order->status === 'cancelled' ? 'bg-gray-100 text-gray-600' : 'bg-brand-soft text-brand' }}">
                {{ $order->statusLabel() }}
            </span>
        </div>

        <div class="grid gap-6 md:grid-cols-[minmax(0,1fr)_18rem]">
            <section class="rounded-2xl border border-gray-200 bg-white" aria-labelledby="order-items-title">
                <h2 id="order-items-title" class="border-b border-gray-100 px-6 py-4 font-semibold text-gray-900">Sản phẩm</h2>
                <ul class="divide-y divide-gray-100">
                    @foreach ($order->items as $item)
                        <li class="flex items-start justify-between gap-4 px-6 py-4 text-sm">
                            <div class="min-w-0">
                                <p class="font-medium text-gray-900">{{ $item->product_name }}</p>
                                <p class="mt-0.5 text-xs text-gray-500">{{ $item->color_name }} · {{ $item->size_name }} · SKU {{ $item->sku }}</p>
                                <p class="mt-1 text-xs text-gray-500">
                                    <x-money :amount="$item->unit_price" /> × {{ $item->quantity }}
                                    @if ((float) $item->discount_amount > 0)
                                        <s class="ml-1 text-gray-400"><x-money :amount="$item->original_unit_price" /></s>
                                    @endif
                                </p>
                            </div>
                            <x-money :amount="$item->line_total" class="shrink-0 font-semibold" />
                        </li>
                    @endforeach
                </ul>
                <dl class="space-y-2.5 border-t border-gray-100 px-6 py-5 text-sm">
                    <div class="flex justify-between"><dt class="text-gray-500">Tạm tính</dt><dd><x-money :amount="$order->subtotal" /></dd></div>
                    @if ((float) $order->discount_amount > 0)
                        <div class="flex justify-between text-brand"><dt>Mã giảm giá</dt><dd>−<x-money :amount="$order->discount_amount" /></dd></div>
                    @endif
                    <div class="flex justify-between"><dt class="text-gray-500">Phí vận chuyển</dt><dd><x-money :amount="$order->shipping_fee" /></dd></div>
                    <div class="flex justify-between border-t border-gray-100 pt-3 text-base font-semibold">
                        <dt>Tổng thanh toán</dt><dd class="text-brand"><x-money :amount="$order->grand_total" /></dd>
                    </div>
                </dl>
            </section>

            <div class="space-y-6">
                <section class="rounded-2xl border border-gray-200 bg-white p-5 text-sm" aria-labelledby="order-shipping-title">
                    <h2 id="order-shipping-title" class="flex items-center gap-2 font-semibold text-gray-900"><x-icon name="truck" class="size-4 text-brand" /> Giao đến</h2>
                    <p class="mt-3 font-medium text-gray-900">{{ $order->customer_name }}</p>
                    <p class="text-gray-600">{{ $order->customer_phone }}</p>
                    <p class="mt-1 leading-6 text-gray-600">{{ $order->shipping_address }}, {{ $order->ward_name }}, {{ $order->district_name }}, {{ $order->province_name }}</p>
                    @if ($order->customer_note)
                        <p class="mt-3 rounded-lg bg-gray-50 px-3 py-2 text-gray-600">Ghi chú: {{ $order->customer_note }}</p>
                    @endif
                </section>

                <section class="rounded-2xl border border-gray-200 bg-white p-5 text-sm" aria-labelledby="order-payment-title">
                    <h2 id="order-payment-title" class="flex items-center gap-2 font-semibold text-gray-900"><x-icon name="revenue" class="size-4 text-brand" /> Thanh toán</h2>
                    <p class="mt-3 text-gray-900">{{ $order->paymentMethodLabel() }}</p>
                    <p class="text-gray-500">{{ $order->paymentStatusLabel() }}</p>
                </section>

                @if ($order->status === 'pending')
                    <form method="POST" action="{{ route('orders.cancel', $order) }}"
                          x-data x-on:submit="if (! confirm('Hủy đơn hàng {{ $order->order_number }}?')) $event.preventDefault()">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="btn btn-secondary w-full hover:border-red-300 hover:bg-red-50 hover:text-red-600">Hủy đơn hàng</button>
                        <p class="mt-2 text-center text-xs text-gray-500">Chỉ hủy được khi cửa hàng chưa xác nhận đơn.</p>
                    </form>
                @endif
            </div>
        </div>

        <a href="{{ route('products.index') }}" class="mt-8 inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-brand">
            <x-icon name="back" class="size-3.5" /> Tiếp tục mua sắm
        </a>
    </div>
@endsection
