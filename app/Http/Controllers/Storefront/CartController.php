<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\AddToCartRequest;
use App\Http\Requests\Storefront\UpdateCartItemRequest;
use App\Models\CartItem;
use App\Models\ProductVariant;
use App\Services\Cart\CartException;
use App\Services\Cart\CartLine;
use App\Services\Cart\ShoppingCart;
use App\Services\Pricing\PriceCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class CartController extends Controller
{
    public function __construct(private ShoppingCart $cart) {}

    /**
     * Totals only count lines that can be bought right now; problem lines are
     * flagged so the customer fixes them before checking out.
     */
    public function index(Request $request, PriceCalculator $prices): View
    {
        $lines = $this->cart->lines($request->user()->activeCart);
        $quote = $prices->quote($lines->filter(fn (CartLine $line): bool => $line->isAvailable())
            ->map(fn (CartLine $line) => $line->price)
            ->values());

        return view('storefront.cart.index', [
            'lines' => $lines,
            'quote' => $quote,
            'hasProblems' => $lines->contains(fn (CartLine $line): bool => ! $line->isAvailable()),
        ]);
    }

    public function store(AddToCartRequest $request): RedirectResponse
    {
        $variant = ProductVariant::withTrashed()
            ->with(['product' => fn ($query) => $query->withTrashed()])
            ->findOrFail($request->integer('product_variant_id'));

        try {
            $this->cart->add($request->user(), $variant, $request->integer('quantity'));
        } catch (CartException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('status', 'Đã thêm vào giỏ hàng.');
    }

    public function update(UpdateCartItemRequest $request, CartItem $cartItem): RedirectResponse
    {
        Gate::authorize('update', $cartItem);

        try {
            $this->cart->updateQuantity($cartItem, $request->integer('quantity'));
        } catch (CartException $exception) {
            return redirect()->route('cart.index')->with('error', $exception->getMessage());
        }

        return redirect()->route('cart.index');
    }

    public function destroy(CartItem $cartItem): RedirectResponse
    {
        Gate::authorize('delete', $cartItem);

        $cartItem->delete();

        return redirect()->route('cart.index')->with('status', 'Đã xóa sản phẩm khỏi giỏ hàng.');
    }
}
