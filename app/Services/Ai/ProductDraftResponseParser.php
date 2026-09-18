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
     * @return array{name: string, description: string, bullets: array<int, string>, seo_title: string, price: string, sizes: array<int, string>, colors: array<int, string>, category: string, brand: string, variant_images: array<int, array{color: string, image_index: int}>}
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

        foreach (['name', 'description', 'bullets', 'seo_title', 'price', 'sizes', 'colors', 'category', 'brand', 'variant_images'] as $key) {
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
            'category' => trim($stringField($decoded['category'])),
            'brand' => trim($stringField($decoded['brand'])),
            'variant_images' => $this->variantImages($decoded['variant_images']),
        ];
    }

    /**
     * Malformed entries are dropped rather than failing the whole response —
     * this is a "nice to have" mapping the widget can also just leave blank.
     *
     * @return array<int, array{color: string, image_index: int}>
     */
    private function variantImages(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $entries = [];
        foreach ($value as $item) {
            if (! is_array($item)) {
                continue;
            }
            $color = is_string($item['color'] ?? null) ? trim($item['color']) : '';
            $index = $item['image_index'] ?? null;
            if ($color === '' || ! is_numeric($index) || (int) $index < 0) {
                continue;
            }
            $entries[] = ['color' => $color, 'image_index' => (int) $index];
        }

        return $entries;
    }
}
