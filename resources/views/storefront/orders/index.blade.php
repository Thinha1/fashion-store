@extends('layouts.app')

@section('title', 'Đơn hàng của tôi — ' . config('app.name', 'Fashion Store'))

@section('content')
    <div class="container page narrow">
        <nav aria-label="Breadcrumb" class="crumbs">
            <ol>
                <li><a href="{{ route('profile.edit') }}">Tài khoản</a></li>
                <li aria-current="page">Đơn hàng của tôi</li>
            </ol>
        </nav>
        <header class="catalog-head">
            <div class="catalog-title">
                <h1 id="orders-title">Đơn hàng của tôi</h1>
            </div>
        </header>

        @if ($orders->isEmpty())
            <div class="catalog-empty">
                <h2>Bạn chưa có đơn hàng nào.</h2>
                <p>Đơn bạn đặt sẽ hiện ở đây, kèm trạng thái giao hàng và thanh toán.</p>
                <a href="{{ route('products.index') }}" class="btn btn-primary">Bắt đầu mua sắm</a>
            </div>
        @else
            <ul class="order-list" role="list" aria-labelledby="orders-title">
                @foreach ($orders as $order)
                    <li>
                        <a href="{{ route('orders.show', $order) }}" class="order-row">
                            <span class="order-row-head">
                                <strong>{{ $order->order_number }}</strong>
                                <span class="status @if ($order->status === 'cancelled') is-muted @endif">{{ $order->statusLabel() }}</span>
                            </span>
                            <span class="caption">
                                <time datetime="{{ $order->placed_at->toIso8601String() }}">{{ $order->placed_at->format('H:i d/m/Y') }}</time>,
                                {{ $order->items_count }} sản phẩm
                            </span>
                            <span class="order-row-foot">
                                <span class="order-row-items">{{ $order->items->pluck('product_name')->join(', ') }}</span>
                                <x-money :amount="$order->grand_total" class="order-row-total" />
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
            <div class="catalog-pages">{{ $orders->links('components.pagination-links') }}</div>
        @endif
    </div>
@endsection
