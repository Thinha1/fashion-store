<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductAiPdfRequest;
use App\Services\Ai\PdfTextExtractor;
use Illuminate\Http\JsonResponse;

/**
 * Turns an uploaded PDF into plain text for the product chat widget. It calls
 * no model and saves nothing: the widget attaches the text to the staff
 * member's next message, the AI drafts the product from it as it would from
 * photos and typed notes, and the staff member still reviews and submits the
 * form themselves.
 */
class ProductAiPdfController extends Controller
{
    public function __invoke(ProductAiPdfRequest $request, PdfTextExtractor $extractor): JsonResponse
    {
        return response()->json(['data' => $extractor->extract($request->file('file')->getRealPath())]);
    }
}
