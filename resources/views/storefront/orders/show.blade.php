@extends('layouts.app')

@section('title', 'Đơn hàng ' . $order->order_number . ' — ' . config('app.name', 'Fashion Store'))

@section('content')
    <div class="container page">
        <nav aria-label="Breadcrumb" class="crumbs">
            <ol>
                <li><a href="{{ route('profile.edit') }}">Tài khoản</a></li>
                <li><a href="{{ route('orders.index') }}">Đơn hàng của tôi</a></li>
                <li aria-current="page">{{ $order->order_number }}</li>
            </ol>
        </nav>
        <header class="catalog-head">
            <div class="catalog-title">
                <h1>{{ $order->order_number }}</h1>
                <p class="caption">Đặt lúc <time datetime="{{ $order->placed_at->toIso8601String() }}">{{ $order->placed_at->format('H:i d/m/Y') }}</time></p>
            </div>
            <span class="status status--lg @if ($order->status === 'cancelled') is-muted @endif">{{ $order->statusLabel() }}</span>
        </header>

        @if ($awaitingTransfer && $bankTransfer->isEnabled())
            @php
                $transferFacts = [
                    ['Ngân hàng', $bankTransfer->bankId(), false],
                    ['Số tài khoản', $bankTransfer->accountNumber(), true],
                    ['Chủ tài khoản', $bankTransfer->accountName(), false],
                    ['Số tiền', number_format((float) $order->grand_total, 0, ',', '.').' ₫', false],
                    ['Nội dung chuyển khoản', $bankTransfer->transferMemo($order), true],
                ];
            @endphp
            <section class="transfer" aria-labelledby="transfer-title"
                     x-data="paymentStatusPoller({ url: {{ Js::from(route('orders.payment-status', $order)) }}, current: {{ Js::from($order->payment_status) }} })">
                <img src="{{ $bankTransfer->qrImageUrl($order) }}" alt="Mã QR chuyển khoản cho đơn {{ $order->order_number }}"
                     class="transfer-qr" width="208" height="208">
                <div class="transfer-body">
                    <h2 id="transfer-title">Quét mã QR để chuyển khoản</h2>
                    <p>Mở ứng dụng ngân hàng và quét mã, số tiền và nội dung đã được điền sẵn. Vui lòng <strong>giữ nguyên nội dung</strong> để hệ thống tự xác nhận.</p>
                    <dl class="summary">
                        @foreach ($transferFacts as [$label, $value, $copyable])
                            <div>
                                <dt>{{ $label }}</dt>
                                <dd class="transfer-value">
                                    {{ $value }}
                                    @if ($copyable)
                                        <button type="button" class="text-link"
                                                x-data="{ copied: false }"
                                                x-on:click="navigator.clipboard.writeText({{ Js::from($value) }}).then(() => { copied = true; setTimeout(() => copied = false, 2000) })"
                                                x-text="copied ? 'Đã chép' : 'Chép'" aria-label="Chép {{ mb_strtolower($label) }}">Chép</button>
                                    @endif
                                </dd>
                            </div>
                        @endforeach
                    </dl>
                    <p class="caption" role="status">Đang chờ tiền về, trang sẽ tự cập nhật khi cửa hàng nhận được thanh toán.</p>
                    <p class="caption">Đã chuyển khoản mà lâu chưa được xác nhận (ví dụ do sửa nội dung chuyển khoản)? Vui lòng liên hệ cửa hàng, nhân viên sẽ kiểm tra tài khoản và xác nhận giúp bạn.</p>
                </div>
            </section>
        @endif

        <div class="cart">
            <section aria-labelledby="order-items-title">
                <h2 id="order-items-title" class="visually-hidden">Sản phẩm</h2>
                <ul class="cart-lines" role="list" aria-labelledby="order-items-title">
                    @foreach ($order->items as $item)
                        <li class="order-item">
                            <div>
                                <p class="cart-name">{{ $item->product_name }}</p>
                                <p class="caption">{{ $item->color_name }}, size {{ $item->size_name }}, SKU {{ $item->sku }}</p>
                                <p class="caption">
                                    <x-money :amount="$item->unit_price" /> × {{ $item->quantity }}
                                    @if ((float) $item->discount_amount > 0)
                                        <s><x-money :amount="$item->original_unit_price" /></s>
                                    @endif
                                </p>
                            </div>
                            <x-money :amount="$item->line_total" class="order-row-total" />
                        </li>
                    @endforeach
                </ul>
                <dl class="summary order-totals">
                    <div><dt>Tạm tính</dt><dd><x-money :amount="$order->subtotal" /></dd></div>
                    @if ((float) $order->discount_amount > 0)
                        <div class="summary-discount"><dt>Mã giảm giá</dt><dd>−<x-money :amount="$order->discount_amount" /></dd></div>
                    @endif
                    <div><dt>Phí vận chuyển</dt><dd><x-money :amount="$order->shipping_fee" /></dd></div>
                    @if ((float) $order->shipping_discount_amount > 0)
                        <div class="summary-discount"><dt>Giảm phí vận chuyển</dt><dd>−<x-money :amount="$order->shipping_discount_amount" /></dd></div>
                    @endif
                    <div class="summary-total"><dt>Tổng thanh toán</dt><dd><x-money :amount="$order->grand_total" /></dd></div>
                </dl>
            </section>

            <aside class="cart-summary" aria-label="Giao hàng và thanh toán">
                <section aria-labelledby="order-shipping-title" class="order-facts">
                    <h2 id="order-shipping-title">Giao đến</h2>
                    <p class="choice-title">{{ $order->customer_name }}</p>
                    <p class="caption">{{ $order->customer_phone }}</p>
                    <p>{{ $order->fullShippingAddress() }}</p>
                    @if ($order->customer_note)
                        <p class="order-note">Ghi chú: {{ $order->customer_note }}</p>
                    @endif
                </section>

                <section aria-labelledby="order-payment-title" class="order-facts">
                    <h2 id="order-payment-title">Thanh toán</h2>
                    <p class="choice-title">{{ $order->paymentMethodLabel() }}</p>
                    <p class="caption">{{ $order->paymentStatusLabel() }}</p>
                </section>

                @if ($order->status === 'pending')
                    <form method="POST" action="{{ route('orders.cancel', $order) }}" class="order-cancel"
                          x-data x-on:submit="if (! confirm('Hủy đơn hàng {{ $order->order_number }}?')) $event.preventDefault()">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="btn btn-secondary btn-block btn-danger">Hủy đơn hàng</button>
                        <p class="caption">Chỉ hủy được khi cửa hàng chưa xác nhận đơn.</p>
                    </form>
                @endif

                <a href="{{ route('products.index') }}" class="text-link">Tiếp tục mua sắm</a>
            </aside>
        </div>
    </div>
@endsection
