<?php

namespace App\Services\Ai;

use App\Models\Brand;
use App\Models\Discount;
use App\Models\Product;
use App\Services\Ai\Actions\Concerns\DescribesAmounts;
use Illuminate\Support\Collection;

/**
 * Settles what the assistant's discount `set_fields` mean for the form that is
 * on screen: fields that form does not have are dropped (and the staff member
 * told which kind of discount has them), and brand and product names become
 * ids the picker can tick. The model only ever says names; it never picks an id.
 */
class DiscountFieldResolver
{
    use DescribesAmounts;

    /** @var Collection<int, Product>|null */
    private ?Collection $products = null;

    /**
     * @param  list<array<string, mixed>>  $entries  from DiscountFormFields::sanitize
     * @param  array<string, mixed>  $context  the form_context the widget sent (kind, mode)
     * @return array{0: list<array<string, mixed>>, 1: list<string>} the entries to apply and what to tell the staff member
     */
    public function resolve(array $entries, array $context): array
    {
        $kind = array_key_exists($context['kind'] ?? '', Discount::SCOPE_LABELS) ? $context['kind'] : 'variant';
        $allowed = DiscountFormFields::fieldsFor($kind, ($context['mode'] ?? '') === 'edit');

        $kept = [];
        $missing = [];
        $notes = [];
        foreach ($entries as $entry) {
            if (! in_array($entry['field'], $allowed, true)) {
                $missing[] = DiscountFormFields::LABELS[$entry['field']];

                continue;
            }

            if (in_array($entry['field'], ['brands', 'products'], true)) {
                [$entry, $problems] = $this->resolveNames($entry);
                array_push($notes, ...$problems);
                if (count($entry) === 1) {
                    continue;
                }
            }

            $kept[] = $entry;
        }

        if ($missing !== []) {
            array_unshift($notes, 'Form “'.Discount::SCOPE_LABELS[$kind].'” không có mục: '.implode(', ', array_unique($missing)).'. Hãy mở đúng loại giảm giá rồi nhờ lại nhé.');
        }

        return [$kept, $notes];
    }

    /**
     * @param  array<string, mixed>  $entry
     * @return array{0: array<string, mixed>, 1: list<string>}
     */
    private function resolveNames(array $entry): array
    {
        $problems = [];
        foreach (['value', 'add', 'remove'] as $op) {
            if (! array_key_exists($op, $entry)) {
                continue;
            }

            $found = [];
            foreach ($entry[$op] as $name) {
                $match = $entry['field'] === 'brands' ? $this->brand($name, $problems) : $this->product($name, $problems);
                if ($match !== null) {
                    $found[$match['id']] = $match;
                }
            }
            // An empty "value" stays: it is how the list is cleared.
            if ($found !== [] || ($op === 'value' && $entry[$op] === [])) {
                $entry[$op] = array_values($found);
            } else {
                unset($entry[$op]);
            }
        }

        return [$entry, $problems];
    }

    /**
     * @param  list<string>  $problems
     * @return array{id: int, name: string}|null
     */
    private function brand(string $name, array &$problems): ?array
    {
        $brand = $this->named(Brand::class, $name)->first();
        if ($brand === null) {
            $problems[] = "Không có thương hiệu “{$name}”.";

            return null;
        }

        return ['id' => (int) $brand->id, 'name' => (string) $brand->name];
    }

    /**
     * An exact name, else the one product whose name contains it; several
     * candidates are not guessed between.
     *
     * @param  list<string>  $problems
     * @return array{id: int, name: string}|null
     */
    private function product(string $name, array &$problems): ?array
    {
        $this->products ??= Product::query()->orderBy('name')->get(['id', 'name']);
        $wanted = mb_strtolower($name);

        $exact = $this->products->filter(fn (Product $product): bool => mb_strtolower($product->name) === $wanted);
        $candidates = $exact->isNotEmpty() ? $exact : $this->products->filter(fn (Product $product): bool => str_contains(mb_strtolower($product->name), $wanted));

        if ($candidates->count() === 1) {
            $product = $candidates->first();

            return ['id' => (int) $product->id, 'name' => (string) $product->name];
        }

        $problems[] = $candidates->isEmpty()
            ? "Không tìm thấy sản phẩm “{$name}”."
            : "Có nhiều sản phẩm khớp “{$name}” ({$candidates->take(3)->pluck('name')->implode(', ')}…), hãy gọi rõ hơn.";

        return null;
    }
}
