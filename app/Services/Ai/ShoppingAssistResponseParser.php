<?php

namespace App\Services\Ai;

use App\Models\ProductVariant;

/**
 * Extracts and validates the search-filter JSON object from the AI's raw
 * text reply for the customer-facing shopping-assist widget. Mirrors
 * ProductDraftResponseParser's tolerant-parsing approach, but the shape is
 * a search filter, not product content — see ShoppingAssistPromptBuilder.
 */
class ShoppingAssistResponseParser
{
    /**
     * @return array{category: string, price_min: int|null, price_max: int|null, sizes: array<int, string>, colors: array<int, string>, keywords: array<int, string>, reply: string}
     *
     * @throws InvalidAiResponseException
     */
    public function parse(string $raw): array
    {
        $json = trim($raw);
        // Tolerate a ```json ... ``` fence even though the prompt asks the model not to use one.
        if (str_starts_with($json, '```')) {
            $json = preg_replace('/(?:\A```[a-z]*\n?)|(?:\n?```\z)/i', '', $json) ?? $json;
        }

        $decoded = json_decode(trim($json), true);

        if (! is_array($decoded)) {
            throw new InvalidAiResponseException('AI response is not a JSON object.');
        }

        foreach (['category', 'price_min', 'price_max', 'sizes', 'colors', 'keywords', 'reply'] as $key) {
            if (! array_key_exists($key, $decoded)) {
                throw new InvalidAiResponseException("AI response is missing the \"{$key}\" field.");
            }
        }

        $stringField = fn (mixed $value): string => is_string($value) ? trim($value) : '';

        $priceMin = $this->toPositiveInt($decoded['price_min']);
        $priceMax = $this->toPositiveInt($decoded['price_max']);

        // A hallucinated/inconsistent range (min above max) would otherwise
        // silently query to an empty result with no feedback to the
        // customer — swapping them is the only sensible recovery, since
        // both values genuinely came from the same "budget" the customer
        // described.
        if ($priceMin !== null && $priceMax !== null && $priceMin > $priceMax) {
            [$priceMin, $priceMax] = [$priceMax, $priceMin];
        }

        return [
            'category' => $stringField($decoded['category']),
            'price_min' => $priceMin,
            'price_max' => $priceMax,
            'sizes' => $this->allowedSizes($decoded['sizes']),
            'colors' => $this->stringList($decoded['colors'], 5),
            // A hallucinated/overlong keyword list is trimmed rather than
            // failing the whole reply — these only ever feed a LIKE search,
            // never get shown to the customer verbatim.
            'keywords' => $this->stringList($decoded['keywords'], 5),
            'reply' => $stringField($decoded['reply']),
        ];
    }

    private function toPositiveInt(mixed $value): ?int
    {
        if (! is_string($value) && ! is_int($value)) {
            return null;
        }

        $digits = preg_replace('/\D/', '', (string) $value) ?? '';

        return $digits === '' ? null : (int) $digits;
    }

    /**
     * @return array<int, string>
     */
    private function allowedSizes(mixed $value): array
    {
        return array_values(array_intersect($this->stringList($value, 10), ProductVariant::SIZES));
    }

    /**
     * @return array<int, string>
     */
    private function stringList(mixed $value, int $limit): array
    {
        if (! is_array($value)) {
            return [];
        }

        $items = array_values(array_filter(array_map(
            fn (mixed $item): string => is_string($item) ? trim($item) : '',
            $value,
        ), fn (string $item): bool => $item !== ''));

        return array_slice($items, 0, $limit);
    }
}
