<?php

namespace App\Services\Ai\Actions;

/**
 * What a confirmed action did, shown in the chat with links to the records.
 */
final readonly class ActionResult
{
    /**
     * @param  list<array{label: string, url: string}>  $links
     * @param  list<string>  $problems  items that could not be done (the rest were)
     */
    public function __construct(
        public string $message,
        public array $links = [],
        public array $problems = [],
    ) {}
}
