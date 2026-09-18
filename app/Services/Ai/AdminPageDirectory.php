<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Route;

/**
 * The fixed set of admin pages the product-assist chat can offer to
 * navigate to (the "navigate" tool). Kept as a plain JSON map — not
 * hardcoded route names in the prompt — so adding/removing a destination
 * never touches the prompt builder, parser, or JS widget.
 */
class AdminPageDirectory
{
    private const PATH = 'data/admin-pages.json';

    /** @var array<string, array{label: string, route: string}>|null */
    private ?array $pages = null;

    /**
     * @return array<string, array{label: string, route: string}>
     */
    public function all(): array
    {
        if ($this->pages === null) {
            $decoded = json_decode(file_get_contents(resource_path(self::PATH)), true);
            $this->pages = is_array($decoded) ? $decoded : [];
        }

        return $this->pages;
    }

    /**
     * The "key: label" list injected into the system prompt, one per line.
     */
    public function describeForPrompt(): string
    {
        $lines = [];
        foreach ($this->all() as $key => $page) {
            $lines[] = "- {$key}: {$page['label']}";
        }

        return implode("\n", $lines);
    }

    /**
     * Resolves a page key the model returned into the label + real URL —
     * or null when the key is empty, unknown, or its route doesn't exist
     * (a hallucinated key is silently ignored, never sent to the browser).
     *
     * @return array{key: string, label: string, url: string}|null
     */
    public function resolve(string $key): ?array
    {
        $page = $this->all()[$key] ?? null;

        if (! $page || ! Route::has($page['route'])) {
            return null;
        }

        return ['key' => $key, 'label' => $page['label'], 'url' => route($page['route'])];
    }
}
