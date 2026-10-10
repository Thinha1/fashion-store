<?php

namespace App\Http\Controllers\Storefront;

use App\Actions\PlaceOrder;
use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\PlaceOrderRequest;
use App\Models\Address;
use App\Services\Cart\CartException;
use App\Services\Cart\CartLine;
use App\Services\Cart\ShoppingCart;
use App\Services\Payments\BankTransfer;
use App\Services\Pricing\InvalidCouponException;
use App\Services\Pricing\PriceCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    /**
     * Review page. A coupon typed in the small "Áp dụng" form comes back as
     * `?ma-giam-gia=` and is previewed here; PlaceOrder checks it again.
     */
    public function create(Request $request, ShoppingCart $cart, PriceCalculator $prices, BankTransfer $bankTransfer): View|RedirectResponse
    {
        $user = $request->user();
        $lines = $cart->lines($user->activeCart);

        if ($lines->isEmpty()) {
            return redirect()->route('cart.index')->with('error', 'Giỏ hàng của bạn đang trống.');
        }

        if ($lines->contains(fn (CartLine $line): bool => ! $line->isAvailable())) {
            return redirect()->route('cart.index')->with('error', 'Vui lòng xử lý các sản phẩm được đánh dấu đỏ trước khi thanh toán.');
        }

        $linePrices = $lines->map(fn (CartLine $line) => $line->price)->values();
        $couponCode = trim((string) $request->query('ma-giam-gia', old('coupon_code', '')));
        $couponError = null;

        try {
            $coupon = $couponCode === '' ? null : $prices->findCoupon($couponCode);
            $quote = $prices->quote($linePrices, $coupon, $user);
        } catch (InvalidCouponException $exception) {
            $couponError = $exception->getMessage();
            $couponCode = '';
            $quote = $prices->quote($linePrices);
        }

        return view('storefront.checkout.create', [
            'lines' => $lines,
            'quote' => $quote,
            'couponCode' => $couponCode,
            'couponError' => $couponError,
            'addresses' => $user->addresses()->orderByDesc('is_default')->latest('id')->get(),
            'bankTransferEnabled' => $bankTransfer->isEnabled(),
        ]);
    }

    public function store(PlaceOrderRequest $request, PlaceOrder $placeOrder): RedirectResponse
    {
        $user = $request->user();

        $address = $request->input('address_id') === 'new'
            ? $this->saveNewAddress($request)
            : $user->addresses()->findOrFail($request->integer('address_id'));

        try {
            $order = $placeOrder->execute($user, $address, $request->safe()->only(['payment_method', 'coupon_code', 'customer_note']));
        } catch (InvalidCouponException $exception) {
            return redirect()->route('checkout.create')->withInput()->withErrors(['coupon_code' => $exception->getMessage()]);
        } catch (CartException $exception) {
            return redirect()->route('cart.index')->with('error', $exception->getMessage());
        }

        return redirect()->route('orders.show', $order)->with('status', $order->payment_method === 'bank_transfer'
            ? 'Đặt hàng thành công! Vui lòng quét mã QR bên dưới để chuyển khoản.'
            : 'Đặt hàng thành công! Cảm ơn bạn đã mua sắm.');
    }

    /**
     * A new address typed at checkout also goes into the address book; it
     * becomes the default when the customer had none.
     */
    private function saveNewAddress(PlaceOrderRequest $request): Address
    {
        $user = $request->user();
        $address = $user->addresses()->create($request->safe()->only([
            'label', 'recipient_name', 'phone', 'province_code', 'province_name', 'district_name', 'ward_name', 'address_line',
        ]));

        if (! $user->addresses()->where('is_default', true)->exists()) {
            $address->markAsDefault();
        }

        return $address;
    }
}
