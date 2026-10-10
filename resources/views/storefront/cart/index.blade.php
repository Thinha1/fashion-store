@extends('layouts.app')

@section('title', 'Giỏ hàng — ' . config('app.name', 'Fashion Store'))

@section('content')
    <div class="mb-8">
        <p class="eyebrow">Mua sắm</p>
        <h1 class="display-title mt-2 text-3xl sm:text-4xl">Giỏ hàng</h1>
    </div>

    @if ($lines->isEmpty())
        <div class="flex flex-col items-center gap-4 rounded-2xl border border-dashed border-gray-300 bg-white px-6 py-16 text-center">
            <x-icon name="cart" class="size-8 text-gray-300" />
            <p class="text-sm text-gray-500">Giỏ hàng của bạn đang trống.</p>
            <a href="{{ route('products.index') }}" class="btn btn-primary">Tiếp tục mua sắm <x-icon class="size-4" /></a>
        </div>
    @else
        <div class="grid gap-8 lg:grid-cols-[minmax(0,1fr)_22rem]">
            <ul class="divide-y divide-gray-100 rounded-2xl border border-gray-200 bg-white">
                @foreach ($lines as $line)
                    @php
                        $variant = $line->variant();
                        $product = $variant->product;
                        $image = $line->image();
                    @endphp
                    <li class="flex gap-4 p-4 sm:p-5">
                        <div class="size-24 shrink-0 overflow-hidden rounded-xl bg-gray-100">
                            @if ($image)
                                <img src="{{ \Illuminate\Support\Facades\Storage::disk(config('filesystems.image_disk'))->url($image->path) }}"
                                     alt="{{ $image->alt_text ?: $product?->name }}" class="size-full object-cover" loading="lazy">
                            @else
                                <div class="flex size-full items-center justify-center text-gray-300"><x-icon name="shirt" class="size-8" /></div>
                            @endif
                        </div>

                        <div class="flex min-w-0 flex-1 flex-col gap-2">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    @if ($product && $line->isAvailable())
                                        <a href="{{ route('products.show', $product) }}" class="font-medium text-gray-900 hover:text-brand">{{ $product->name }}</a>
                                    @else
                                        <p class="font-medium text-gray-900">{{ $product?->name ?? 'Sản phẩm' }}</p>
                                    @endif
                                    <p class="mt-0.5 text-xs text-gray-500">Màu {{ $variant->color }} · Size {{ $variant->size }}</p>
                                </div>
                                <form method="POST" action="{{ route('cart.destroy', $line->item) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="p-1 text-gray-400 hover:text-red-600" aria-label="Xóa {{ $product?->name }} khỏi giỏ hàng">
                                        <x-icon name="delete" class="size-4" />
                                    </button>
                                </form>
                            </div>

                            @if ($line->problem)
                                <p class="flex items-center gap-1.5 text-xs font-medium text-red-600"><x-icon name="info" class="size-3.5" /> {{ $line->problem }}</p>
                            @endif

                            <div class="mt-auto flex flex-wrap items-end justify-between gap-3">
                                {{-- The typed quantity comes first in the DOM, so a clicked −/+ button's
                                     own quantity value is sent last and wins; CSS order restores the layout. --}}
                                <form method="POST" action="{{ route('cart.update', $line->item) }}" class="inline-flex items-center rounded-xl border border-gray-200">
                                    @csrf
                                    @method('PATCH')
                                    {{-- Default button for Enter in the input, so it doesn't fall to "−". --}}
                                    <button type="submit" class="sr-only" tabindex="-1" aria-hidden="true"></button>
                                    <input type="number" name="quantity" value="{{ $line->item->quantity }}" min="1" max="{{ \App\Services\Cart\ShoppingCart::MAX_LINE_QUANTITY }}"
                                           x-data x-on:change="$el.form.requestSubmit()" aria-label="Số lượng"
                                           class="order-2 w-12 [appearance:textfield] border-0 bg-transparent p-0 text-center text-sm font-semibold focus:ring-0 [&::-webkit-inner-spin-button]:appearance-none">
                                    <button type="submit" name="quantity" value="{{ $line->item->quantity - 1 }}" @disabled($line->item->quantity <= 1)
                                            class="order-1 flex size-9 items-center justify-center text-gray-500 hover:text-brand disabled:opacity-40" aria-label="Giảm số lượng">
                                        <x-icon name="minus" class="size-3" />
                                    </button>
                                    <button type="submit" name="quantity" value="{{ $line->item->quantity + 1 }}"
                                            class="order-3 flex size-9 items-center justify-center text-gray-500 hover:text-brand" aria-label="Tăng số lượng">
                                        <x-icon name="plus" class="size-3" />
                                    </button>
                                </form>

                                <div class="text-right">
                                    <x-money :amount="$line->price->lineTotal()" class="block font-semibold text-gray-900" />
                                    <p class="text-xs text-gray-500">
                                        <x-money :amount="$line->price->unitPrice" />
                                        @if ($line->price->isDiscounted())
                                            <s class="ml-1 text-gray-400"><x-money :amount="$line->price->originalUnitPrice" /></s>
                                        @endif
                                    </p>
                                </div>
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>

            <aside class="h-fit rounded-2xl border border-gray-200 bg-white p-6 lg:sticky lg:top-6" aria-labelledby="cart-summary-title">
                <h2 id="cart-summary-title" class="font-semibold text-gray-900">Tóm tắt đơn hàng</h2>
                <dl class="mt-5 space-y-3 text-sm">
                    <div class="flex justify-between"><dt class="text-gray-500">Tạm tính</dt><dd><x-money :amount="$quote->subtotal" /></dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">Phí vận chuyển</dt><dd><x-money :amount="$quote->shippingFee" /></dd></div>
                    <div class="flex justify-between border-t border-gray-100 pt-3 text-base font-semibold">
                        <dt>Tổng cộng</dt><dd class="text-brand"><x-money :amount="$quote->grandTotal" /></dd>
                    </div>
                </dl>
                <p class="mt-2 text-xs text-gray-500">Mã giảm giá được nhập ở bước thanh toán.</p>

                @if ($hasProblems)
                    <p class="mt-5 rounded-xl bg-red-50 px-3 py-2.5 text-xs text-red-700">Vui lòng xóa hoặc giảm số lượng các sản phẩm được đánh dấu đỏ trước khi thanh toán.</p>
                    <span class="btn btn-primary mt-4 w-full cursor-not-allowed opacity-50" aria-disabled="true">Thanh toán</span>
                @else
                    <a href="{{ route('checkout.create') }}" class="btn btn-primary mt-5 w-full">Thanh toán <x-icon class="size-4" /></a>
                @endif

                <a href="{{ route('products.index') }}" class="mt-3 block text-center text-sm text-gray-500 hover:text-brand">Tiếp tục mua sắm</a>
            </aside>
        </div>
    @endif
@endsection
