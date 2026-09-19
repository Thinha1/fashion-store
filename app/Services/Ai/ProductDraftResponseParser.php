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
     * @return array{name: string, description: string, bullets: array<int, string>, seo_title: string, price: string, sizes: array<int, string>, colors: array<int, string>, category: string, brand: string, variant_images: array<int, array{color: string, image_index: int}>, navigate: string, set_fields: array<int, array{field: string, value: string|array<int, string>}>, variant_price: string, variant_stock: string}
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

        $stringField = fn (mixed $value): string => is_string($value) ? $value : '';

        // Only the original "compose a product" fields are hard-required —
        // a reply missing one of these is genuinely malformed. The newer
        // additive "tool" fields below (navigate/set_fields/variant_*) are
        // treated as optional instead: as more of them get added to the
        // schema over time, an otherwise-fine reply from an imperfect model
        // is increasingly likely to simply omit one it isn't using, and
        // that shouldn't hard-fail the whole turn — a missing key means the
        // same thing as an explicitly empty one ("no tool used this turn").
        foreach (['name', 'description', 'bullets', 'seo_title', 'price', 'sizes', 'colors', 'category', 'brand', 'variant_images'] as $key) {
            if (! array_key_exists($key, $decoded)) {
                throw new InvalidAiResponseException("AI response is missing the \"{$key}\" field.");
            }
        }

        return [
            'name' => trim($stringField($decoded['name'])),
            'description' => trim($stringField($decoded['description'])),
            'bullets' => $this->stringList($decoded['bullets']),
            'seo_title' => trim($stringField($decoded['seo_title'])),
            'price' => trim($stringField($decoded['price'])),
            'sizes' => $this->stringList($decoded['sizes']),
            'colors' => $this->stringList($decoded['colors']),
            'category' => trim($stringField($decoded['category'])),
            'brand' => trim($stringField($decoded['brand'])),
            'variant_images' => $this->variantImages($decoded['variant_images']),
            // Raw key as the model returned it — AdminPageDirectory (called
            // by the controller) is what validates it against real pages.
            'navigate' => trim($stringField($decoded['navigate'] ?? '')),
            // Quick single/few-field edits (see ProductDraftPromptBuilder) —
            // applied straight to the live form, no draft-card review step,
            // so only a small whitelisted set of simple fields is accepted.
            'set_fields' => $this->setFields($decoded['set_fields'] ?? []),
            // Per-variant overrides — distinct from "price" above, which is
            // the product's own base price. Applied uniformly to every
            // variant row this turn creates (see buildFillPlan/applySetFields).
            'variant_price' => trim($stringField($decoded['variant_price'] ?? '')),
            'variant_stock' => trim($stringField($decoded['variant_stock'] ?? '')),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn (mixed $item): string => is_string($item) ? trim($item) : '',
            $value,
        ), fn (string $item): bool => $item !== ''));
    }

    /**
     * Malformed entries and anything outside the fixed field whitelist are
     * dropped rather than failing the whole response.
     *
     * @return array<int, array{field: string, value: string|array<int, string>}>
     */
    private function setFields(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        // "sizes"/"colors" edit the variant rows (a list), everything else
        // is a single form field (a string). Deliberately excludes
        // bullets/seo_title/variant_images — those only ever exist as part
        // of a full composed draft (no dedicated form field of their own to
        // write straight into), so they stay behind the draft card's review
        // step instead.
        $listFields = ['sizes', 'colors'];
        $stringFields = ['name', 'price', 'category', 'brand', 'variant_price', 'variant_stock', 'description', 'status'];

        $entries = [];
        foreach ($value as $item) {
            if (! is_array($item)) {
                continue;
            }
            $field = is_string($item['field'] ?? null) ? trim($item['field']) : '';

            if (in_array($field, $listFields, true)) {
                // An empty list is kept, not dropped — it's how the model
                // clears a field ("xoá hết size"), distinct from the field
                // simply not being mentioned this turn (no entry at all).
                $entries[] = ['field' => $field, 'value' => $this->stringList($item['value'] ?? null)];
            } elseif (in_array($field, $stringFields, true)) {
                // Same reasoning: a present-but-empty "value" is a deliberate
                // clear signal, so it's kept as long as it's actually a
                // string — only a missing/non-string "value" is malformed.
                if (! is_string($item['value'] ?? null)) {
                    continue;
                }
                $fieldValue = trim($item['value']);
                // "status" directly flips a product's storefront visibility —
                // unlike the other string fields, a hallucinated value here
                // (e.g. "deleted") must never pass through silently, so it's
                // restricted to exactly the two real values instead of just
                // trusting whatever string the model sent.
                if ($field === 'status' && ! in_array($fieldValue, ['active', 'archived'], true)) {
                    continue;
                }
                $entries[] = ['field' => $field, 'value' => $fieldValue];
            }
        }

        return $entries;
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
