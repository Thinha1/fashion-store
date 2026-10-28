@extends('layouts.app')

@section('title', 'Giỏ hàng — ' . config('app.name', 'Fashion Store'))

@section('content')
    <div class="container page">
        <header class="catalog-head">
            <div class="catalog-title">
                <h1 id="cart-title">Giỏ hàng</h1>
                @if ($lines->isNotEmpty())
                    <p class="caption">{{ $lines->sum(fn ($line) => $line->item->quantity) }} sản phẩm</p>
                @endif
            </div>
        </header>

        @if ($lines->isEmpty())
            <div class="catalog-empty">
                <h2>Giỏ hàng của bạn đang trống.</h2>
                <p>Chọn vài món ở trang sản phẩm, chúng sẽ nằm ở đây chờ bạn thanh toán.</p>
                <a href="{{ route('products.index') }}" class="btn btn-primary">Tiếp tục mua sắm</a>
            </div>
        @else
            <div class="cart">
                <ul class="cart-lines" role="list" aria-labelledby="cart-title">
                    @foreach ($lines as $line)
                        @php
                            $variant = $line->variant();
                            $product = $variant->product;
                            $image = $line->image();
                        @endphp
                        <li class="cart-line @if ($line->problem) has-problem @endif">
                            <div class="cart-thumb" data-slot="cart-{{ $line->item->id }}" data-kind="product" data-alt="{{ $product?->name }}">
                                @if ($image)
                                    <img src="{{ \Illuminate\Support\Facades\Storage::disk(config('filesystems.image_disk'))->url($image->path) }}"
                                         alt="{{ $image->alt_text ?: $product?->name }}" width="240" height="300" loading="lazy">
                                @endif
                            </div>

                            <div class="cart-info">
                                <div class="cart-info-head">
                                    <div>
                                        @if ($product && $line->isAvailable())
                                            <a href="{{ route('products.show', $product) }}" class="cart-name">{{ $product->name }}</a>
                                        @else
                                            <p class="cart-name">{{ $product?->name ?? 'Sản phẩm' }}</p>
                                        @endif
                                        <p class="caption">Màu {{ $variant->color }}, size {{ $variant->size }}</p>
                                    </div>
                                    <form method="POST" action="{{ route('cart.destroy', $line->item) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="cart-remove" aria-label="Xóa {{ $product?->name }} khỏi giỏ hàng" title="Xóa khỏi giỏ">
                                            <x-icon name="delete" />
                                        </button>
                                    </form>
                                </div>

                                @if ($line->problem)
                                    <p class="cart-problem" role="alert"><x-icon name="warning" /> {{ $line->problem }}</p>
                                @endif

                                <div class="cart-info-foot">
                                    {{-- The typed quantity comes first in the DOM, so a clicked −/+ button's
                                         own quantity value is sent last and wins; CSS order restores the layout. --}}
                                    <form method="POST" action="{{ route('cart.update', $line->item) }}" class="stepper cart-stepper">
                                        @csrf
                                        @method('PATCH')
                                        {{-- Default button for Enter in the input, so it doesn't fall to "−". --}}
                                        <button type="submit" class="visually-hidden" tabindex="-1" aria-hidden="true"></button>
                                        <input type="number" name="quantity" value="{{ $line->item->quantity }}" min="1" max="{{ \App\Services\Cart\ShoppingCart::MAX_LINE_QUANTITY }}"
                                               x-data x-on:change="$el.form.requestSubmit()" aria-label="Số lượng" class="num">
                                        <button type="submit" name="quantity" value="{{ $line->item->quantity - 1 }}" @disabled($line->item->quantity <= 1)
                                                class="stepper-minus" aria-label="Giảm số lượng">
                                            <x-icon name="minus" />
                                        </button>
                                        <button type="submit" name="quantity" value="{{ $line->item->quantity + 1 }}"
                                                class="stepper-plus" aria-label="Tăng số lượng">
                                            <x-icon name="plus" />
                                        </button>
                                    </form>

                                    <div class="cart-price">
                                        <x-money :amount="$line->price->lineTotal()" class="price-now" />
                                        <span class="caption">
                                            <x-money :amount="$line->price->unitPrice" /> mỗi món
                                            @if ($line->price->isDiscounted())
                                                <s><x-money :amount="$line->price->originalUnitPrice" /></s>
                                            @endif
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ul>

                <aside class="cart-summary" aria-labelledby="cart-summary-title">
                    <h2 id="cart-summary-title">Tóm tắt đơn hàng</h2>
                    <dl class="summary">
                        <div><dt>Tạm tính</dt><dd><x-money :amount="$quote->subtotal" /></dd></div>
                        <div><dt>Phí vận chuyển</dt><dd><x-money :amount="$quote->shippingFee" /></dd></div>
                        <div class="summary-total"><dt>Tổng cộng</dt><dd><x-money :amount="$quote->grandTotal" /></dd></div>
                    </dl>
                    <p class="caption">Mã giảm giá được nhập ở bước thanh toán.</p>

                    @if ($hasProblems)
                        <p class="cart-problem" role="alert">Vui lòng xóa hoặc giảm số lượng các sản phẩm được đánh dấu đỏ trước khi thanh toán.</p>
                        <span class="btn btn-primary btn-block" aria-disabled="true">Thanh toán</span>
                    @else
                        <a href="{{ route('checkout.create') }}" class="btn btn-primary btn-block">Thanh toán</a>
                    @endif

                    <a href="{{ route('products.index') }}" class="text-link">Tiếp tục mua sắm</a>
                </aside>
            </div>
        @endif
    </div>
@endsection
