<?php

namespace App\Http\Controllers\Admin;

use App\Actions\OrderStatusException;
use App\Actions\ReviewPaymentProof;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReviewPaymentProofRequest;
use App\Models\Order;
use App\Services\Payments\BankTransfer;
use App\Support\AdminPagination;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Manual check of transfer receipts that the SePay webhook couldn't match
 * (BUSINESS_FLOWS.md §6). Oldest receipt first, so nobody waits longest.
 */
class PaymentReviewController extends Controller
{
    public function index(Request $request): View
    {
        $orders = Order::query()
            ->where('payment_status', 'pending_review')
            ->orderBy('payment_proof_submitted_at')
            ->orderBy('id')
            ->paginate(AdminPagination::perPage($request))->withQueryString();

        return view('admin.payments.index', ['orders' => $orders]);
    }

    public function show(Order $order): View
    {
        $order->load(['items', 'paymentReviewer:id,name']);

        return view('admin.payments.show', ['order' => $order]);
    }

    /**
     * The receipt image, from the private disk — never a public URL.
     */
    public function receipt(Order $order, BankTransfer $bankTransfer): StreamedResponse
    {
        $disk = Storage::disk($bankTransfer->proofDisk());

        abort_unless($order->payment_proof_path && $disk->exists($order->payment_proof_path), 404);

        return $disk->response($order->payment_proof_path, null, [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function review(ReviewPaymentProofRequest $request, Order $order, ReviewPaymentProof $reviewPaymentProof): RedirectResponse
    {
        try {
            if ($request->validated('decision') === 'approve') {
                $reviewPaymentProof->approve($order, $request->user());
                $message = "Đã xác nhận thanh toán cho đơn {$order->order_number}.";
            } else {
                $reviewPaymentProof->reject($order, $request->user(), $request->validated('reason'));
                $message = "Đã từ chối chứng từ của đơn {$order->order_number}; khách sẽ thấy lý do và có thể gửi lại.";
            }
        } catch (OrderStatusException $exception) {
            return redirect()->route('admin.payments.index')->with('error', $exception->getMessage());
        }

        return redirect()->route('admin.payments.index')->with('status', $message);
    }
}
