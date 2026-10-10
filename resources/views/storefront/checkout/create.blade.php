@extends('layouts.app')

@section('title', 'Thanh toán — ' . config('app.name', 'Fashion Store'))

@section('content')
    @php
        $defaultAddressId = $addresses->firstWhere('is_default', true)?->id ?? $addresses->first()?->id;
        $addressChoice = (string) old('address_id', $defaultAddressId ?? 'new');
    @endphp

    <a href="{{ route('cart.index') }}" class="mb-5 inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-brand">
        <x-icon name="back" class="size-3.5" /> Giỏ hàng
    </a>
    <div class="mb-8">
        <p class="eyebrow">Bước cuối</p>
        <h1 class="display-title mt-2 text-3xl sm:text-4xl">Thanh toán</h1>
    </div>

    <div class="grid gap-8 lg:grid-cols-[minmax(0,1fr)_24rem]">
        <form id="checkout-form" method="POST" action="{{ route('checkout.store') }}" class="space-y-6"
              x-data="{ addressChoice: {{ Js::from($addressChoice) }} }">
            @csrf
            <input type="hidden" name="coupon_code" value="{{ $couponCode }}">

            <section class="rounded-2xl border border-gray-200 bg-white p-6" aria-labelledby="shipping-title">
                <h2 id="shipping-title" class="flex items-center gap-2 font-semibold text-gray-900"><x-icon name="truck" class="size-4 text-brand" /> Địa chỉ giao hàng</h2>
                <x-input-error :messages="$errors->get('address_id')" class="mt-2" />

                <div class="mt-4 space-y-3">
                    @foreach ($addresses as $address)
                        <label class="flex cursor-pointer gap-3 rounded-xl border p-4 has-checked:border-brand has-checked:bg-brand-soft/40 border-gray-200">
                            <input type="radio" name="address_id" value="{{ $address->id }}" x-model="addressChoice" class="mt-1 accent-brand">
                            <span class="min-w-0 text-sm">
                                <span class="font-semibold text-gray-900">{{ $address->recipient_name }}</span>
                                <span class="text-gray-500">· {{ $address->phone }}</span>
                                @if ($address->is_default)
                                    <span class="ml-1 rounded-md bg-brand-soft px-1.5 py-0.5 text-xs font-semibold text-brand">Mặc định</span>
                                @endif
                                <span class="mt-1 block text-gray-600">{{ $address->fullAddress() }}</span>
                            </span>
                        </label>
                    @endforeach

                    <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-gray-200 p-4 has-checked:border-brand has-checked:bg-brand-soft/40">
                        <input type="radio" name="address_id" value="new" x-model="addressChoice" class="accent-brand">
                        <span class="flex items-center gap-1.5 text-sm font-medium text-gray-900"><x-icon name="plus" class="size-3.5" /> Giao đến địa chỉ mới</span>
                    </label>

                    {{-- Disabled while hidden so the browser neither requires nor submits these fields. --}}
                    <fieldset class="rounded-xl border border-dashed border-gray-300 p-4" x-show="addressChoice === 'new'" x-cloak
                              :disabled="addressChoice !== 'new'" @disabled($addressChoice !== 'new')>
                        <legend class="px-1 text-xs text-gray-500">Địa chỉ mới sẽ được lưu vào sổ địa chỉ của bạn</legend>
                        @include('storefront.addresses._fields', ['address' => new \App\Models\Address])
                    </fieldset>
                </div>
            </section>

            <section class="rounded-2xl border border-gray-200 bg-white p-6" aria-labelledby="payment-title">
                <h2 id="payment-title" class="flex items-center gap-2 font-semibold text-gray-900"><x-icon name="revenue" class="size-4 text-brand" /> Phương thức thanh toán</h2>
                <div class="mt-4 space-y-3">
                    <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-gray-200 p-4 has-checked:border-brand has-checked:bg-brand-soft/40">
                        <input type="radio" name="payment_method" value="cod" class="accent-brand" @checked(old('payment_method', 'cod') === 'cod')>
                        <span class="text-sm">
                            <span class="font-medium text-gray-900">Thanh toán khi nhận hàng (COD)</span>
                            <span class="block text-gray-500">Trả tiền mặt cho nhân viên giao hàng.</span>
                        </span>
                    </label>
                    @if ($bankTransferEnabled)
                        <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-gray-200 p-4 has-checked:border-brand has-checked:bg-brand-soft/40">
                            <input type="radio" name="payment_method" value="bank_transfer" class="accent-brand" @checked(old('payment_method') === 'bank_transfer')>
                            <span class="text-sm">
                                <span class="font-medium text-gray-900">Chuyển khoản ngân hàng (quét mã QR)</span>
                                <span class="block text-gray-500">Mã QR hiện sau khi đặt hàng; đơn được xác nhận thanh toán tự động khi tiền về.</span>
                            </span>
                        </label>
                    @endif
                </div>
                <x-input-error :messages="$errors->get('payment_method')" class="mt-2" />
            </section>

            <section class="rounded-2xl border border-gray-200 bg-white p-6">
                {{-- A plain <label> (not <x-label>) so static analysis sees it is tied to the textarea. --}}
                <label for="customer_note" class="block text-sm font-medium text-gray-700">Ghi chú cho cửa hàng <span class="font-normal text-gray-400">(không bắt buộc)</span></label>
                <textarea id="customer_note" name="customer_note" rows="3" maxlength="500" class="field mt-1"
                          placeholder="Ví dụ: giao giờ hành chính">{{ old('customer_note') }}</textarea>
                <x-input-error :messages="$errors->get('customer_note')" />
            </section>
        </form>

        <aside class="h-fit space-y-5 rounded-2xl border border-gray-200 bg-white p-6 lg:sticky lg:top-6" aria-labelledby="order-summary-title">
            <h2 id="order-summary-title" class="font-semibold text-gray-900">Đơn hàng ({{ $lines->sum(fn ($line) => $line->item->quantity) }} sản phẩm)</h2>

            <ul class="max-h-72 space-y-3 overflow-y-auto">
                @foreach ($lines as $line)
                    @php($image = $line->image())
                    <li class="flex gap-3 text-sm">
                        <div class="relative size-14 shrink-0 overflow-hidden rounded-lg bg-gray-100">
                            @if ($image)
                                <img src="{{ \Illuminate\Support\Facades\Storage::disk(config('filesystems.image_disk'))->url($image->path) }}" alt="" class="size-full object-cover" loading="lazy">
                            @endif
                            <span class="absolute top-0.5 right-0.5 rounded-full bg-gray-900/75 px-1.5 text-[0.65rem] font-semibold text-white">{{ $line->item->quantity }}</span>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-medium text-gray-900">{{ $line->variant()->product->name }}</p>
                            <p class="text-xs text-gray-500">{{ $line->variant()->color }} · {{ $line->variant()->size }}</p>
                        </div>
                        <x-money :amount="$line->price->lineTotal()" class="shrink-0 font-medium" />
                    </li>
                @endforeach
            </ul>

            <div class="border-t border-gray-100 pt-5">
                @if ($couponCode !== '')
                    <div class="flex items-center justify-between rounded-xl bg-brand-soft px-3 py-2.5 text-sm">
                        <span class="flex items-center gap-1.5 font-medium text-brand"><x-icon name="tag" class="size-3.5" /> {{ $couponCode }}</span>
                        <a href="{{ route('checkout.create') }}" class="text-xs text-gray-500 hover:text-red-600">Bỏ mã</a>
                    </div>
                @else
                    <form method="GET" action="{{ route('checkout.create') }}" class="flex gap-2">
                        <label for="coupon" class="sr-only">Mã giảm giá</label>
                        <x-input id="coupon" name="ma-giam-gia" placeholder="Mã giảm giá" class="min-h-10 uppercase placeholder:normal-case" />
                        <button type="submit" class="btn btn-secondary min-h-10 shrink-0 px-4">Áp dụng</button>
                    </form>
                @endif
                <x-input-error :messages="$couponError ?? $errors->get('coupon_code')" />
            </div>

            <dl class="space-y-2.5 border-t border-gray-100 pt-5 text-sm">
                <div class="flex justify-between"><dt class="text-gray-500">Tạm tính</dt><dd><x-money :amount="$quote->subtotal" /></dd></div>
                @if ($quote->discountAmount > 0)
                    <div class="flex justify-between text-brand"><dt>Mã giảm giá</dt><dd>−<x-money :amount="$quote->discountAmount" /></dd></div>
                @endif
                <div class="flex justify-between"><dt class="text-gray-500">Phí vận chuyển</dt><dd><x-money :amount="$quote->shippingFee" /></dd></div>
                <div class="flex justify-between border-t border-gray-100 pt-3 text-base font-semibold">
                    <dt>Tổng thanh toán</dt><dd class="text-brand"><x-money :amount="$quote->grandTotal" /></dd>
                </div>
            </dl>

            <button type="submit" form="checkout-form" class="btn btn-primary w-full">Đặt hàng</button>
            <p class="text-center text-xs text-gray-500">Giá và tồn kho được kiểm tra lại khi bạn bấm Đặt hàng.</p>
        </aside>
    </div>
@endsection
