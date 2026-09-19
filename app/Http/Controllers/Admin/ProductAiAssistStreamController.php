<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductAiAssistRequest;
use App\Models\Brand;
use App\Models\Category;
use App\Services\Ai\AdminPageDirectory;
use App\Services\Ai\AiProviderContract;
use App\Services\Ai\ProductDraftPromptBuilder;
use App\Services\Ai\ProductDraftResolver;
use App\Services\Ai\SseAssistStream;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Same call as ProductAiAssistController, but forwards the AI's output as it
 * streams in (Server-Sent Events) instead of waiting for the whole reply —
 * so the widget can show live progress instead of a blank "thinking" bubble
 * for as long as a self-hosted model takes. The final "done" event carries
 * the exact same {data, navigate, raw} shape the JSON endpoint returns, both
 * built by the same ProductDraftResolver — this controller only changes the
 * transport (see SseAssistStream, shared with the storefront assistant),
 * never the parsing/validation, and the JSON endpoint/route is left
 * untouched as the widget's fallback.
 */
class ProductAiAssistStreamController extends Controller
{
    private const UNAVAILABLE = 'Chưa thể tạo nội dung gợi ý lúc này. Vui lòng thử lại sau hoặc nhập thông tin thủ công.';

    public function __invoke(
        ProductAiAssistRequest $request,
        AiProviderContract $provider,
        ProductDraftPromptBuilder $promptBuilder,
        ProductDraftResolver $resolver,
        AdminPageDirectory $pages,
        SseAssistStream $stream,
    ): StreamedResponse {
        $messages = $request->validated('messages');
        $categories = Category::query()->where('is_active', true)->orderBy('name')->pluck('name')->all();
        $brands = Brand::query()->where('is_active', true)->orderBy('name')->pluck('name')->all();
        $systemPrompt = $promptBuilder->build($categories, $brands, $pages->describeForPrompt());

        return $stream->respond(
            $provider,
            $messages,
            $systemPrompt,
            fn (string $reply) => $resolver->resolve($reply),
            self::UNAVAILABLE,
            'Product AI assist (stream): reply failed to parse.',
            'AI trả về nội dung không đúng định dạng. Vui lòng thử lại hoặc diễn đạt lại yêu cầu.',
        );
    }
}
