<?php

namespace App\Services\Ai;

use App\Models\ProductVariant;
use App\Support\ColorPalette;

/**
 * Extracts and validates the product-draft JSON object from the AI's raw
 * text reply. Kept separate from the provider classes so that both
 * providers — and any future one — share the exact same validation of the
 * shape the widget is allowed to render/fill into the form.
 */
class ProductDraftResponseParser
{
    /**
     * @param  string  $screen  the form the staff member is on: "product" or "discount" — decides which
     *                          `set_fields` whitelist applies (DiscountFormFields for the discount form)
     * @return array{name: string, description: string, bullets: array<int, string>, seo_title: string, price: string, sizes: array<int, string>, colors: array<int, string>, category: string, brand: string, variant_images: array<int, array{color: string, image_index: int}>, navigate: string, navigate_product: string, set_fields: array<int, array<string, mixed>>, variant_price: string, variant_stock: string, actions: list<array<string, mixed>>}
     *
     * @throws InvalidAiResponseException
     */
    public function parse(string $raw, string $screen = 'product'): array
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
        // A reply that is a tool call (a quick edit or a page change) leaves
        // the compose fields empty anyway, and models sometimes drop them.
        $isToolReply = ! empty($decoded['set_fields']) || ! empty($decoded['navigate']) || ! empty($decoded['navigate_product']) || ! empty($decoded['actions']);
        foreach (['name', 'description', 'bullets', 'seo_title', 'price', 'sizes', 'colors', 'category', 'brand', 'variant_images'] as $key) {
            if ($isToolReply) {
                $decoded[$key] ??= in_array($key, ['bullets', 'sizes', 'colors', 'variant_images'], true) ? [] : '';
            } elseif (! array_key_exists($key, $decoded)) {
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
            'colors' => $this->paletteColors($decoded['colors']),
            'category' => trim($stringField($decoded['category'])),
            'brand' => trim($stringField($decoded['brand'])),
            'variant_images' => $this->variantImages($decoded['variant_images']),
            // Raw key as the model returned it — AdminPageDirectory (called
            // by the controller) is what validates it against real pages.
            'navigate' => trim($stringField($decoded['navigate'] ?? '')),
            // How the staff member named a product to open; ProductLocator
            // (called by the resolver) finds the real product.
            'navigate_product' => trim($stringField($decoded['navigate_product'] ?? '')),
            // Quick single/few-field edits (see ProductDraftPromptBuilder) —
            // applied straight to the live form, no draft-card review step,
            // so only a small whitelisted set of simple fields is accepted.
            'set_fields' => $screen === 'discount'
                ? (new DiscountFormFields)->sanitize($decoded['set_fields'] ?? [])
                : $this->setFields($decoded['set_fields'] ?? []),
            // Per-variant overrides — distinct from "price" above, which is
            // the product's own base price. Applied uniformly to every
            // variant row this turn creates (see buildFillPlan/applySetFields).
            'variant_price' => trim($stringField($decoded['variant_price'] ?? '')),
            'variant_stock' => trim($stringField($decoded['variant_stock'] ?? '')),
            // Proposed admin actions (AgentActionRegistry): only the shape is
            // checked here; each action validates its own parameters.
            'actions' => $this->actions($decoded['actions'] ?? []),
        ];
    }

    /**
     * Up to five {type, ...params} objects; anything without a string type is dropped.
     *
     * @return list<array<string, mixed>>
     */
    private function actions(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_slice(array_values(array_filter(
            $value,
            fn (mixed $action): bool => is_array($action) && is_string($action['type'] ?? null) && $action['type'] !== '',
        )), 0, 5);
    }

    /**
     * @return array<int, string>
     */
    /**
     * Colors must be palette names (the form's color field is a select);
     * free-text ones are matched to the palette, the rest dropped.
     *
     * @return array<int, string>
     */
    private function paletteColors(mixed $value): array
    {
        return array_values(array_unique(array_filter(array_map(
            ColorPalette::match(...),
            $this->stringList($value),
        ))));
    }

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
     * "sizes"/"colors" are edits of the variant list: any of value (the full
     * wanted list — [] clears it), add, remove. "variant_price"/"variant_stock"
     * may carry match {sizes, colors} to reach only some variants.
     *
     * @return array<int, array<string, mixed>>
     */
    private function setFields(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        // Deliberately excludes bullets/seo_title/variant_images — those only
        // ever exist as part of a full composed draft (no dedicated form field
        // of their own to write straight into), so they stay behind the draft
        // card's review step instead.
        $listFields = ['sizes', 'colors'];
        $stringFields = ['name', 'price', 'category', 'brand', 'variant_price', 'variant_stock', 'description', 'status'];

        $entries = [];
        foreach ($value as $item) {
            if (! is_array($item)) {
                continue;
            }
            $field = is_string($item['field'] ?? null) ? trim($item['field']) : '';

            if (in_array($field, $listFields, true)) {
                $normalize = $field === 'colors' ? $this->paletteColors(...) : $this->sizes(...);
                $entry = ['field' => $field];
                foreach (['value', 'add', 'remove'] as $op) {
                    // A present-but-empty "value" is kept: it is how the model
                    // clears the list ("xoá hết size"), distinct from the field
                    // not being mentioned this turn (no entry at all).
                    if (is_array($item[$op] ?? null)) {
                        $entry[$op] = $normalize($item[$op]);
                    }
                }
                if (count($entry) > 1) {
                    $entries[] = $entry;
                }
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
                $entry = ['field' => $field, 'value' => $fieldValue];
                if (in_array($field, ['variant_price', 'variant_stock'], true) && is_array($item['match'] ?? null)) {
                    $match = array_filter([
                        'sizes' => $this->sizes($item['match']['sizes'] ?? null),
                        'colors' => $this->paletteColors($item['match']['colors'] ?? null),
                    ]);
                    // A match that names nothing real would silently widen
                    // to "every variant" — drop the entry instead.
                    if ($match === []) {
                        continue;
                    }
                    $entry['match'] = $match;
                }
                $entries[] = $entry;
            }
        }

        return $entries;
    }

    /**
     * Sizes the variant select offers (ProductVariant::SIZES); XXL/XXXL are
     * read as 2XL/3XL, anything else is dropped.
     *
     * @return array<int, string>
     */
    private function sizes(mixed $value): array
    {
        $aliases = ['XXL' => '2XL', 'XXXL' => '3XL', 'XXXXL' => '4XL'];

        return array_values(array_unique(array_filter(array_map(function (string $size) use ($aliases): ?string {
            $size = strtoupper(str_replace(' ', '', $size));
            $size = $aliases[$size] ?? $size;

            return in_array($size, ProductVariant::SIZES, true) ? $size : null;
        }, $this->stringList($value)))));
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
            $color = ColorPalette::match(is_string($item['color'] ?? null) ? $item['color'] : null) ?? '';
            $index = $item['image_index'] ?? null;
            if ($color === '' || ! is_numeric($index) || (int) $index < 0) {
                continue;
            }
            $entries[] = ['color' => $color, 'image_index' => (int) $index];
        }

        return $entries;
    }
}
