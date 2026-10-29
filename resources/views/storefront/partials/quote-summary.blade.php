{{-- Totals of a quote (cart and checkout): subtotal, order voucher, shipping fee,
     shipping voucher, total, and how much more to buy for free shipping. --}}
@php($voucherTitle = fn ($voucher, string $kind) => ($voucher->is_auto_apply ? 'Ưu đãi tự động' : $kind).' ('.$voucher->voucherLabel().')')
<dl class="summary">
    <div><dt>Tạm tính</dt><dd><x-money :amount="$quote->subtotal" /></dd></div>
    @if ($quote->discountAmount > 0)
        <div class="summary-discount"><dt>{{ $voucherTitle($quote->coupon, 'Mã giảm giá') }}</dt><dd>−<x-money :amount="$quote->discountAmount" /></dd></div>
    @endif
    <div><dt>Phí vận chuyển</dt><dd><x-money :amount="$quote->shippingFee" /></dd></div>
    @if ($quote->shippingDiscountAmount > 0)
        <div class="summary-discount"><dt>{{ $voucherTitle($quote->shippingVoucher, 'Giảm phí vận chuyển') }}</dt><dd>−<x-money :amount="$quote->shippingDiscountAmount" /></dd></div>
    @endif
    <div class="summary-total"><dt>{{ $totalLabel ?? 'Tổng cộng' }}</dt><dd><x-money :amount="$quote->grandTotal" /></dd></div>
</dl>
@if ($quote->discountAmount > 0 && ($scope = $quote->coupon?->targetSummary()))
    <p class="summary-hint">{{ $quote->coupon->voucherLabel() }} chỉ giảm trên {{ $scope }}.</p>
@endif
@if ($quote->freeShippingGap)
    <p class="summary-hint">Mua thêm <x-money :amount="$quote->freeShippingGap" /> để được miễn phí vận chuyển.</p>
@endif
