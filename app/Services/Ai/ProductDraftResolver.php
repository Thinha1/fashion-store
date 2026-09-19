<?php

namespace App\Services\Ai;

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
    ) {}

    /**
     * @return array{data: array<string, mixed>, navigate: array{key: string, label: string, url: string}|null, raw: string}
     *
     * @throws InvalidAiResponseException
     */
    public function resolve(string $reply): array
    {
        $draft = $this->responseParser->parse($reply);

        // A hallucinated/unknown key just silently resolves to null — never
        // sent to the browser as if it were a real destination.
        $navigate = $this->pages->resolve($draft['navigate']);
        unset($draft['navigate']);

        // "raw" is echoed back so the widget can push the AI's own words into
        // its local conversation history — the next turn must resend exactly
        // what the model said, not our normalized re-encoding of it.
        return ['data' => $draft, 'navigate' => $navigate, 'raw' => $reply];
    }
}
