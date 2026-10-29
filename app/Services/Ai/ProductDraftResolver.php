<?php

namespace App\Services\Ai;

use App\Models\User;
use App\Services\Ai\Actions\AgentActionProposals;
use App\Services\Ai\Actions\AgentActionRegistry;
use App\Services\Ai\Actions\InvalidActionException;
use App\Services\Catalog\ProductLocator;

/**
 * Turns a raw AI reply into the exact payload shape both the JSON and
 * streaming product-assist endpoints send to the browser — kept in one
 * place so the two transports (see ProductAiAssistController and
 * ProductAiAssistStreamController) never diverge on parsing/validation.
 */
class ProductDraftResolver
{
    public function __construct(
        private readonly ProductDraftResponseParser $responseParser,
        private readonly AdminPageDirectory $pages,
        private readonly ProductLocator $products,
        private readonly AgentActionRegistry $actions,
        private readonly AgentActionProposals $proposals,
    ) {}

    /**
     * @return array{data: array<string, mixed>, navigate: array{key: string, label: string, url: string}|null, notice: string|null, actions: list<array<string, mixed>>, raw: string}
     *
     * @throws InvalidAiResponseException
     */
    public function resolve(string $reply, ?User $actor = null): array
    {
        $draft = $this->responseParser->parse($reply);

        // A hallucinated/unknown key just silently resolves to null — never
        // sent to the browser as if it were a real destination.
        $navigate = $this->pages->resolve($draft['navigate']);
        $notice = null;

        // A product the staff member asked to open is looked up here, never
        // taken as an id/URL from the model.
        if ($draft['navigate_product'] !== '') {
            $product = $this->products->find($draft['navigate_product']);
            $navigate = $product
                ? ['key' => 'products.edit', 'label' => 'sửa sản phẩm “'.$product->name.'”', 'url' => route('admin.products.edit', $product)]
                : null;
            $notice = $product ? null : 'Không tìm thấy sản phẩm “'.$draft['navigate_product'].'”. Bạn thử gọi bằng tên khác hoặc SKU nhé.';
        }
        $actions = $actor ? array_map(fn (array $action) => $this->previewAction($action, $actor), $draft['actions']) : [];
        unset($draft['navigate'], $draft['navigate_product'], $draft['actions']);

        // "raw" is echoed back so the widget can push the AI's own words into
        // its local conversation history — the next turn must resend exactly
        // what the model said, not our normalized re-encoding of it.
        return ['data' => $draft, 'navigate' => $navigate, 'notice' => $notice, 'actions' => $actions, 'raw' => $reply];
    }

    /**
     * A card for one proposed action: its preview and the id to confirm it
     * with, or the reason it cannot be done. Unknown types and actions the
     * staff member lacks the permission for read the same way.
     *
     * @param  array<string, mixed>  $proposal
     * @return array<string, mixed>
     */
    private function previewAction(array $proposal, User $actor): array
    {
        $type = $proposal['type'];
        $action = $this->actions->forUser($type, $actor);
        if (! $action) {
            return ['type' => $type, 'error' => 'Bạn chưa có quyền dùng thao tác này, hoặc trợ lý chưa làm được việc này.'];
        }

        try {
            $preview = $action->preview($proposal, $actor);
        } catch (InvalidActionException $exception) {
            return ['type' => $type, 'error' => $exception->getMessage()];
        }

        return [
            'type' => $type,
            'id' => $this->proposals->store($actor, $type, $preview->params),
            'title' => $preview->title,
            'lines' => $preview->lines,
            'warnings' => $preview->warnings,
            'confirm_label' => $preview->confirmLabel,
        ];
    }
}
