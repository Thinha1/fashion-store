<?php

namespace App\Services\Ai;

/**
 * Extracts and validates the product-draft JSON object from the AI's raw
 * text reply. Kept separate from the provider classes so that both
 * providers — and any future one — share the exact same validation of the
 * shape the widget is allowed to render/fill into the form.
 */
class ProductDraftResponseParser
{
    /**
     * @return array{name: string, description: string, bullets: array<int, string>, seo_title: string, price: string, sizes: array<int, string>, colors: array<int, string>}
     *
     * @throws InvalidAiResponseException
     */
    public function parse(string $raw): array
    {
        $json = trim($raw);
        // Tolerate a ```json ... ``` fence even though the prompt asks the model not to use one.
        if (str_starts_with($json, '```')) {
            $json = preg_replace('/\A```[a-z]*\n?|\n?```\z/i', '', $json) ?? $json;
        }

        $decoded = json_decode(trim($json), true);

        if (! is_array($decoded)) {
            throw new InvalidAiResponseException('AI response is not a JSON object.');
        }

        $stringField = fn (mixed $value): string => is_string($value) ? $value : '';
        $stringList = function (mixed $value): array {
            if (! is_array($value)) {
                return [];
            }

            return array_values(array_filter(array_map(
                fn (mixed $item): string => is_string($item) ? trim($item) : '',
                $value,
            ), fn (string $item): bool => $item !== ''));
        };

        foreach (['name', 'description', 'bullets', 'seo_title', 'price', 'sizes', 'colors'] as $key) {
            if (! array_key_exists($key, $decoded)) {
                throw new InvalidAiResponseException("AI response is missing the \"{$key}\" field.");
            }
        }

        return [
            'name' => trim($stringField($decoded['name'])),
            'description' => trim($stringField($decoded['description'])),
            'bullets' => $stringList($decoded['bullets']),
            'seo_title' => trim($stringField($decoded['seo_title'])),
            'price' => trim($stringField($decoded['price'])),
            'sizes' => $stringList($decoded['sizes']),
            'colors' => $stringList($decoded['colors']),
        ];
    }
}
