<?php

namespace App\Http\Controllers\Webhooks;

use App\Actions\RecordSepayTransfer;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * POST /webhooks/sepay — SePay reports each transfer into the shop account.
 * No session and no CSRF token (SePay is a server), so the request must
 * carry "Authorization: Apikey <SEPAY_WEBHOOK_API_KEY>". A 2xx with
 * {"success": true} tells SePay to stop retrying, so it is also returned for
 * transfers that match no order — those are kept in the audit log instead.
 */
class SepayWebhookController extends Controller
{
    public function __invoke(Request $request, RecordSepayTransfer $recordTransfer): JsonResponse
    {
        if (! $this->hasValidApiKey($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $validator = Validator::make($request->all(), [
            'id' => ['required', 'integer'],
            'transferType' => ['required', 'string'],
            'transferAmount' => ['required', 'numeric', 'min:0'],
            'content' => ['nullable', 'string', 'max:1000'],
            'code' => ['nullable', 'string', 'max:255'],
            'referenceCode' => ['nullable', 'string', 'max:255'],
            'gateway' => ['nullable', 'string', 'max:255'],
            'transactionDate' => ['nullable', 'string', 'max:50'],
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $outcome = $recordTransfer->execute($validator->validated());

        return response()->json(['success' => true, 'result' => $outcome]);
    }

    private function hasValidApiKey(Request $request): bool
    {
        $expected = (string) config('services.sepay.webhook_api_key');
        $header = (string) $request->header('Authorization');

        if ($expected === '' || ! preg_match('/^Apikey\s+(\S+)$/i', $header, $match)) {
            return false;
        }

        return hash_equals($expected, $match[1]);
    }
}
