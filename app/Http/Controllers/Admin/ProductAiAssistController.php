<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductAiAssistRequest;
use App\Services\Ai\AiProviderContract;
use App\Services\Ai\InvalidAiResponseException;
use App\Services\Ai\ProductDraftPromptBuilder;
use App\Services\Ai\ProductDraftResponseParser;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class ProductAiAssistController extends Controller
{
    private const UNAVAILABLE = 'Chưa thể tạo nội dung gợi ý lúc này. Vui lòng thử lại sau hoặc nhập thông tin thủ công.';

    public function __invoke(
        ProductAiAssistRequest $request,
        AiProviderContract $provider,
        ProductDraftPromptBuilder $promptBuilder,
        ProductDraftResponseParser $responseParser,
    ): JsonResponse {
        $messages = $request->validated('messages');

        try {
            $reply = $provider->complete($messages, $promptBuilder->build());
        } catch (ConnectionException|RuntimeException) {
            abort(503, self::UNAVAILABLE);
        }

        try {
            $draft = $responseParser->parse($reply);
        } catch (InvalidAiResponseException) {
            abort(502, 'AI trả về nội dung không đúng định dạng. Vui lòng thử lại hoặc diễn đạt lại yêu cầu.');
        }

        // "raw" is echoed back so the widget can push the AI's own words into
        // its local conversation history — the next turn must resend exactly
        // what the model said, not our normalized re-encoding of it.
        return response()->json(['data' => $draft, 'raw' => $reply]);
    }
}
