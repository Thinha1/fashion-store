<?php

namespace App\Services\Ai\Actions;

/**
 * What a proposed action would do, shown on its card in the chat before the
 * staff member confirms. `params` are the normalized parameters execute()
 * receives, so the confirmed action is exactly the one previewed.
 */
final readonly class ActionPreview
{
    /**
     * @param  list<string>  $lines  one line per thing that will be created or changed
     * @param  list<string>  $warnings  things the staff member should know before confirming
     * @param  array<string, mixed>  $params
     */
    public function __construct(
        public string $title,
        public array $lines,
        public string $confirmLabel,
        public array $params,
        public array $warnings = [],
    ) {}
}
