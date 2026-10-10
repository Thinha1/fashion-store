<?php

namespace App\Http\Controllers\Storefront;

use App\Actions\OrderStatusException;
use App\Actions\SubmitPaymentProof;
use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\SubmitPaymentProofRequest;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class PaymentProofController extends Controller
{
    public function store(SubmitPaymentProofRequest $request, Order $order, SubmitPaymentProof $submitPaymentProof): RedirectResponse
    {
        Gate::authorize('view', $order);

        try {
            $submitPaymentProof->execute($order, $request->user(), $request->validated('transaction_code'), $request->file('receipt'));
        } catch (OrderStatusException $exception) {
            return redirect()->route('orders.show', $order)->with('error', $exception->getMessage());
        }

        return redirect()->route('orders.show', $order)
            ->with('status', 'Đã gửi chứng từ. Cửa hàng sẽ kiểm tra và xác nhận thanh toán sớm.');
    }
}
