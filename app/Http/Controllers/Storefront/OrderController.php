<?php

namespace App\Http\Controllers\Storefront;

use App\Actions\CancelOrder;
use App\Actions\OrderNotCancellableException;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class OrderController extends Controller
{
    /**
     * The signed-in customer's orders, newest first.
     */
    public function index(Request $request): View
    {
        $orders = $request->user()->orders()
            ->withCount('items')
            ->with(['items' => fn ($query) => $query->select('id', 'order_id', 'product_name')])
            ->latest('placed_at')
            ->latest('id')
            ->paginate(10);

        return view('storefront.orders.index', ['orders' => $orders]);
    }

    public function show(Order $order): View
    {
        Gate::authorize('view', $order);

        $order->load('items');

        return view('storefront.orders.show', ['order' => $order]);
    }

    /**
     * Customers may cancel only while the order is still `pending`.
     */
    public function cancel(Request $request, Order $order, CancelOrder $cancelOrder): RedirectResponse
    {
        Gate::authorize('cancel', $order);

        try {
            $cancelOrder->execute($order, $request->user(), 'Khách hủy đơn');
        } catch (OrderNotCancellableException $exception) {
            return redirect()->route('orders.show', $order)->with('error', $exception->getMessage());
        }

        return redirect()->route('orders.show', $order)->with('status', 'Đã hủy đơn hàng.');
    }
}
