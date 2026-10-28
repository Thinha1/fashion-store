@extends('layouts.app')

@section('title', 'Thanh toán — ' . config('app.name', 'Fashion Store'))

@section('content')
    @php
        $defaultAddressId = $addresses->firstWhere('is_default', true)?->id ?? $addresses->first()?->id;
        $addressChoice = (string) old('address_id', $defaultAddressId ?? 'new');
    @endphp

    <div class="container page">
        <nav aria-label="Breadcrumb" class="crumbs">
            <ol>
                <li><a href="{{ route('cart.index') }}">Giỏ hàng</a></li>
                <li aria-current="page">Thanh toán</li>
            </ol>
        </nav>
        <header class="catalog-head">
            <div class="catalog-title">
                <h1>Thanh toán</h1>
                <p class="caption">Chọn nơi nhận và cách trả tiền, rồi bấm Đặt hàng.</p>
            </div>
        </header>

        <div class="cart">
            <form id="checkout-form" method="POST" action="{{ route('checkout.store') }}" class="checkout"
                  x-data="{ addressChoice: {{ Js::from($addressChoice) }} }">
                @csrf
                <input type="hidden" name="coupon_code" value="{{ $couponCode }}">

                <section class="checkout-step" aria-labelledby="shipping-title">
                    <h2 id="shipping-title">Địa chỉ giao hàng</h2>
                    <x-input-error :messages="$errors->get('address_id')" />

                    <div class="choices">
                        @foreach ($addresses as $address)
                            <label class="choice">
                                <input type="radio" name="address_id" value="{{ $address->id }}" x-model="addressChoice">
                                <span class="choice-body">
                                    <span class="choice-title">{{ $address->recipient_name }}
                                        @if ($address->is_default)
                                            <span class="tag tag--ink">Mặc định</span>
                                        @endif
                                    </span>
                                    <span class="caption">{{ $address->phone }}</span>
                                    <span>{{ $address->fullAddress() }}</span>
                                </span>
                            </label>
                        @endforeach

                        <label class="choice">
                            <input type="radio" name="address_id" value="new" x-model="addressChoice">
                            <span class="choice-body"><span class="choice-title">Giao đến địa chỉ mới</span></span>
                        </label>

                        {{-- Disabled while hidden so the browser neither requires nor submits these fields. --}}
                        <fieldset class="checkout-new-address" x-show="addressChoice === 'new'" x-cloak
                                  :disabled="addressChoice !== 'new'" @disabled($addressChoice !== 'new')>
                            <legend>Địa chỉ mới sẽ được lưu vào sổ địa chỉ của bạn</legend>
                            @include('storefront.addresses._fields', ['address' => new \App\Models\Address])
                        </fieldset>
                    </div>
                </section>

                <section class="checkout-step" aria-labelledby="payment-title">
                    <h2 id="payment-title">Phương thức thanh toán</h2>
                    <div class="choices">
                        <label class="choice">
                            <input type="radio" name="payment_method" value="cod" @checked(old('payment_method', 'cod') === 'cod')>
                            <span class="choice-body">
                                <span class="choice-title">Thanh toán khi nhận hàng (COD)</span>
                                <span class="caption">Trả tiền mặt cho nhân viên giao hàng.</span>
                            </span>
                        </label>
                        @if ($bankTransferEnabled)
                            <label class="choice">
                                <input type="radio" name="payment_method" value="bank_transfer" @checked(old('payment_method') === 'bank_transfer')>
                                <span class="choice-body">
                                    <span class="choice-title">Chuyển khoản ngân hàng (quét mã QR)</span>
                                    <span class="caption">Mã QR hiện sau khi đặt hàng; đơn được xác nhận thanh toán tự động khi tiền về.</span>
                                </span>
                            </label>
                        @endif
                    </div>
                    <x-input-error :messages="$errors->get('payment_method')" />
                </section>

                <section class="checkout-step" aria-labelledby="note-title">
                    <h2 id="note-title">Ghi chú</h2>
                    {{-- A plain <label> (not <x-label>) so static analysis sees it is tied to the textarea. --}}
                    <label for="customer_note" class="field-label">Ghi chú cho cửa hàng <span class="caption">(không bắt buộc)</span></label>
                    <textarea id="customer_note" name="customer_note" rows="3" maxlength="500" class="field"
                              placeholder="Ví dụ: giao giờ hành chính">{{ old('customer_note') }}</textarea>
                    <x-input-error :messages="$errors->get('customer_note')" />
                </section>
            </form>

            <aside class="cart-summary" aria-labelledby="order-summary-title">
                <h2 id="order-summary-title">Đơn hàng ({{ $lines->sum(fn ($line) => $line->item->quantity) }} sản phẩm)</h2>

                <ul class="checkout-lines" role="list">
                    @foreach ($lines as $line)
                        @php($image = $line->image())
                        <li>
                            <div class="checkout-thumb" data-slot="checkout-{{ $line->item->id }}" data-kind="product" data-alt="{{ $line->variant()->product->name }}">
                                @if ($image)
                                    <img src="{{ \Illuminate\Support\Facades\Storage::disk(config('filesystems.image_disk'))->url($image->path) }}" alt="" width="120" height="150" loading="lazy">
                                @endif
                                <span class="checkout-qty" data-keep aria-label="Số lượng {{ $line->item->quantity }}">{{ $line->item->quantity }}</span>
                            </div>
                            <div class="checkout-line-text">
                                <p class="cart-name-sm">{{ $line->variant()->product->name }}</p>
                                <p class="caption">{{ $line->variant()->color }}, {{ $line->variant()->size }}</p>
                            </div>
                            <x-money :amount="$line->price->lineTotal()" class="checkout-line-total" />
                        </li>
                    @endforeach
                </ul>

                <div class="checkout-coupon">
                    @if ($couponCode !== '')
                        <div class="checkout-coupon-applied">
                            <span class="tag">{{ $couponCode }}</span>
                            <a href="{{ route('checkout.create') }}" class="text-link">Bỏ mã</a>
                        </div>
                    @else
                        <form method="GET" action="{{ route('checkout.create') }}" class="checkout-coupon-form">
                            <label for="coupon" class="visually-hidden">Mã giảm giá</label>
                            <x-input id="coupon" name="ma-giam-gia" placeholder="Mã giảm giá" />
                            <button type="submit" class="btn btn-secondary">Áp dụng</button>
                        </form>
                    @endif
                    <x-input-error :messages="$couponError ?? $errors->get('coupon_code')" />
                </div>

                <dl class="summary">
                    <div><dt>Tạm tính</dt><dd><x-money :amount="$quote->subtotal" /></dd></div>
                    @if ($quote->discountAmount > 0)
                        <div class="summary-discount"><dt>Mã giảm giá</dt><dd>−<x-money :amount="$quote->discountAmount" /></dd></div>
                    @endif
                    <div><dt>Phí vận chuyển</dt><dd><x-money :amount="$quote->shippingFee" /></dd></div>
                    <div class="summary-total"><dt>Tổng thanh toán</dt><dd><x-money :amount="$quote->grandTotal" /></dd></div>
                </dl>

                <button type="submit" form="checkout-form" class="btn btn-primary btn-block">Đặt hàng</button>
                <p class="caption">Giá và tồn kho được kiểm tra lại khi bạn bấm Đặt hàng.</p>
            </aside>
        </div>
    </div>
@endsection
