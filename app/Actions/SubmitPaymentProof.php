<?php

namespace App\Actions;

use App\Models\AuditLog;
use App\Models\Order;
use App\Models\User;
use App\Services\Payments\BankTransfer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Manual fallback when the SePay webhook hasn't matched a transfer
 * (BUSINESS_FLOWS.md §6): the customer sends the bank's transaction code and
 * a screenshot; the order goes to `pending_review` for staff to check.
 * The file is stored first and removed again if the order can't take it.
 */
class SubmitPaymentProof
{
    public function __construct(private BankTransfer $bankTransfer) {}

    /**
     * @throws OrderStatusException when the order no longer accepts a receipt
     */
    public function execute(Order $order, User $customer, string $transactionCode, UploadedFile $receipt): Order
    {
        $disk = Storage::disk($this->bankTransfer->proofDisk());
        $path = $receipt->store('payment-proofs', $this->bankTransfer->proofDisk());

        try {
            [$lockedOrder, $previousPath] = DB::transaction(function () use ($order, $customer, $transactionCode, $path): array {
                $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

                if (! $this->bankTransfer->canSubmitProof($lockedOrder)) {
                    throw new OrderStatusException('Đơn hàng này hiện không cần gửi chứng từ thanh toán.');
                }

                $previousPath = $lockedOrder->payment_proof_path;
                $previousStatus = $lockedOrder->payment_status;

                $lockedOrder->forceFill([
                    'payment_status' => 'pending_review',
                    'transaction_code' => $transactionCode,
                    'payment_proof_path' => $path,
                    'payment_proof_submitted_at' => now(),
                    'payment_rejection_reason' => null,
                ])->save();

                AuditLog::query()->create([
                    'actor_id' => $customer->id,
                    'action' => 'payment.proof_submitted',
                    'subject_type' => $lockedOrder->getMorphClass(),
                    'subject_id' => $lockedOrder->id,
                    'old_values' => ['payment_status' => $previousStatus],
                    'new_values' => ['payment_status' => 'pending_review', 'transaction_code' => $transactionCode],
                ]);

                return [$lockedOrder, $previousPath];
            });
        } catch (Throwable $exception) {
            $disk->delete($path);

            throw $exception;
        }

        // A re-sent receipt (after a rejection) replaces the old file.
        if ($previousPath && $previousPath !== $path) {
            $disk->delete($previousPath);
        }

        return $lockedOrder;
    }
}
