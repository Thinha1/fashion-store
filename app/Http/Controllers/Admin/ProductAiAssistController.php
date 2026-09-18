<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductAiAssistRequest;
use App\Models\Brand;
use App\Models\Category;
use App\Services\Ai\AdminPageDirectory;
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
        AdminPageDirectory $pages,
    ): JsonResponse {
        $messages = $request->validated('messages');

        // The AI can only pick a category/brand that actually exists by
        // being shown the real list — otherwise it would freely invent a
        // name the storefront has no matching option for. Same idea for
        // "navigate": it can only pick a page key from the real directory.
        $categories = Category::query()->where('is_active', true)->orderBy('name')->pluck('name')->all();
        $brands = Brand::query()->where('is_active', true)->orderBy('name')->pluck('name')->all();

        try {
            $reply = $provider->complete($messages, $promptBuilder->build($categories, $brands, $pages->describeForPrompt()));
        } catch (ConnectionException|RuntimeException) {
            abort(503, self::UNAVAILABLE);
        }

        try {
            $draft = $responseParser->parse($reply);
        } catch (InvalidAiResponseException) {
            abort(502, 'AI trả về nội dung không đúng định dạng. Vui lòng thử lại hoặc diễn đạt lại yêu cầu.');
        }

        // A hallucinated/unknown key just silently resolves to null — never
        // sent to the browser as if it were a real destination.
        $navigate = $pages->resolve($draft['navigate']);
        unset($draft['navigate']);

        // "raw" is echoed back so the widget can push the AI's own words into
        // its local conversation history — the next turn must resend exactly
        // what the model said, not our normalized re-encoding of it.
        return response()->json(['data' => $draft, 'navigate' => $navigate, 'raw' => $reply]);
    }
}
