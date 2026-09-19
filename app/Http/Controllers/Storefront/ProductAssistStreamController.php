<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\ProductAssistRequest;
use App\Models\Category;
use App\Models\ProductVariant;
use App\Services\Ai\AiProviderContract;
use App\Services\Ai\CurrentProductContextDescriber;
use App\Services\Ai\ShoppingAssistPromptBuilder;
use App\Services\Ai\ShoppingAssistResolver;
use App\Services\Ai\SseAssistStream;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Same call as ProductAssistController, but forwards the AI's output as it
 * streams in (Server-Sent Events) instead of waiting for the whole reply —
 * so the widget can show live progress instead of a blank "thinking" bubble
 * for as long as a self-hosted model takes. The final "done" event carries
 * the exact same {reply, products, raw} shape the JSON endpoint returns,
 * both built by the same ShoppingAssistResolver — this controller only
 * changes the transport (see SseAssistStream, shared with the admin
 * assistant), never the parsing/lookup logic, and the JSON endpoint/route is
 * left untouched as the widget's fallback.
 */
class ProductAssistStreamController extends Controller
{
    private const UNAVAILABLE = 'Chưa thể gợi ý lúc này. Vui lòng thử lại sau hoặc tự tìm trong danh mục sản phẩm.';

    public function __invoke(
        ProductAssistRequest $request,
        AiProviderContract $provider,
        ShoppingAssistPromptBuilder $promptBuilder,
        ShoppingAssistResolver $resolver,
        CurrentProductContextDescriber $contextDescriber,
        SseAssistStream $stream,
    ): StreamedResponse {
        $messages = $request->validated('messages');
        $categories = Category::query()->where('is_active', true)->orderBy('name')->pluck('name')->all();
        $systemPrompt = $promptBuilder->build($categories, ProductVariant::SIZES, $contextDescriber->describe($request->validated('context_product_id')));

        $wireMessages = array_map(fn (array $message): array => [
            'role' => $message['role'],
            'content' => [['type' => 'text', 'text' => $message['content']]],
        ], $messages);

        return $stream->respond(
            $provider,
            $wireMessages,
            $systemPrompt,
            fn (string $reply) => $resolver->resolve($reply),
            self::UNAVAILABLE,
            'Shopping assist (stream): reply failed to parse.',
            'AI trả về nội dung không đúng định dạng. Vui lòng thử lại hoặc diễn đạt lại yêu cầu.',
        );
    }
}
