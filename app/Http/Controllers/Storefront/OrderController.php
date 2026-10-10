<?php

namespace App\Http\Controllers\Storefront;

use App\Actions\CancelOrder;
use App\Actions\OrderNotCancellableException;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Payments\BankTransfer;
use Illuminate\Http\JsonResponse;
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

    public function show(Order $order, BankTransfer $bankTransfer): View
    {
        Gate::authorize('view', $order);

        $order->load('items');

        return view('storefront.orders.show', [
            'order' => $order,
            'bankTransfer' => $bankTransfer,
            'awaitingTransfer' => $order->payment_method === 'bank_transfer'
                && in_array($order->payment_status, ['unpaid', 'rejected'], true)
                && $order->status !== 'cancelled',
            'proofUnderReview' => $order->payment_method === 'bank_transfer'
                && $order->payment_status === 'pending_review'
                && $order->status !== 'cancelled',
            'canSubmitProof' => $bankTransfer->canSubmitProof($order),
        ]);
    }

    /**
     * Polled by the order page while a bank transfer is outstanding, so the
     * page can refresh itself once the SePay webhook marks the order paid.
     */
    public function paymentStatus(Order $order): JsonResponse
    {
        Gate::authorize('view', $order);

        return response()->json(['payment_status' => $order->payment_status]);
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
