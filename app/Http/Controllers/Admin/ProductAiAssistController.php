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
use App\Services\Ai\ProductDraftResolver;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class ProductAiAssistController extends Controller
{
    private const UNAVAILABLE = 'Chưa thể tạo nội dung gợi ý lúc này. Vui lòng thử lại sau hoặc nhập thông tin thủ công.';

    public function __invoke(
        ProductAiAssistRequest $request,
        AiProviderContract $provider,
        ProductDraftPromptBuilder $promptBuilder,
        ProductDraftResolver $resolver,
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
            $payload = $resolver->resolve($reply);
        } catch (InvalidAiResponseException $exception) {
            // The raw reply is the only way to diagnose *why* a model
            // response didn't parse (a genuinely malformed core field vs.
            // something the prompt should phrase differently) — without
            // this, a 502 here is a dead end to debug.
            Log::warning('Product AI assist: reply failed to parse.', [
                'reason' => $exception->getMessage(),
                'raw' => Str::limit($reply, 2000),
            ]);
            abort(502, 'AI trả về nội dung không đúng định dạng. Vui lòng thử lại hoặc diễn đạt lại yêu cầu.');
        }

        return response()->json($payload);
    }
}
