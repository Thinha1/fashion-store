<?php

namespace App\Services\Ai;

use App\Services\Ai\Actions\Concerns\DescribesAmounts;
use App\Services\Ai\Actions\InvalidActionException;

/**
 * The `set_fields` entries the assistant may send for the discount form (the
 * "discount" screen of ProductDraftResponseParser): a fixed whitelist, each
 * field cleaned to what the form's own input takes. Anything else, and any
 * value that does not make sense for its field, is dropped rather than
 * failing the reply. Names of brands and products are resolved to ids later
 * (DiscountFieldResolver); here they stay names.
 */
class DiscountFormFields
{
    use DescribesAmounts;

    public const LABELS = [
        'name' => 'tên ưu đãi',
        'code' => 'mã voucher',
        'is_auto_apply' => 'tự động áp dụng',
        'discount_type' => 'kiểu giảm',
        'discount_value' => 'mức giảm',
        'max_discount_amount' => 'giảm tối đa',
        'min_order_amount' => 'đơn tối thiểu',
        'usage_limit' => 'tổng lượt dùng',
        'usage_limit_per_customer' => 'lượt dùng mỗi khách',
        'starts_at' => 'bắt đầu',
        'ends_at' => 'kết thúc',
        'is_active' => 'đang hoạt động',
        'applies_to' => 'phạm vi áp dụng',
        'brands' => 'thương hiệu',
        'products' => 'sản phẩm',
    ];

    private const SHARED = ['discount_type', 'discount_value', 'max_discount_amount', 'starts_at', 'ends_at', 'is_active'];

    private const VOUCHER = ['name', 'code', 'is_auto_apply', 'min_order_amount', 'usage_limit', 'usage_limit_per_customer'];

    /**
     * The fields the form of this kind has on screen: a new variant discount
     * picks brands and products but has no code; an order voucher adds the
     * "áp dụng cho" switch; a shipping voucher has no picks; editing one
     * variant discount has none either.
     *
     * @return list<string>
     */
    public static function fieldsFor(string $kind, bool $editing): array
    {
        return match ($kind) {
            'shipping' => [...self::SHARED, ...self::VOUCHER],
            'order' => [...self::SHARED, ...self::VOUCHER, 'applies_to', 'brands', 'products'],
            default => $editing ? self::SHARED : [...self::SHARED, 'brands', 'products'],
        };
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function sanitize(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $entries = [];
        foreach ($value as $item) {
            if (! is_array($item) || ! is_string($item['field'] ?? null)) {
                continue;
            }

            $field = trim($item['field']);
            $entry = match ($field) {
                'name' => $this->text($item, 120),
                'code' => $this->code($item),
                'discount_type' => $this->choice($item, ['percent', 'fixed']),
                'applies_to' => $this->choice($item, ['all', 'selected']),
                'is_auto_apply', 'is_active' => $this->flag($item),
                'discount_value' => $this->amount($item, clearable: false),
                'max_discount_amount', 'min_order_amount' => $this->amount($item, clearable: true),
                'usage_limit', 'usage_limit_per_customer' => $this->count($item),
                'starts_at' => $this->moment_($item, 'ngày bắt đầu', false),
                'ends_at' => $this->moment_($item, 'ngày kết thúc', true),
                'brands', 'products' => $this->names($item),
                default => null,
            };

            if ($entry !== null) {
                $entries[] = ['field' => $field] + $entry;
            }
        }

        return $entries;
    }

    /** @return array{value: string}|null */
    private function text(array $item, int $max): ?array
    {
        return is_string($item['value'] ?? null) ? ['value' => mb_substr(trim($item['value']), 0, $max)] : null;
    }

    /** Upper case, as stored; an empty value clears the field. */
    private function code(array $item): ?array
    {
        if (! is_string($item['value'] ?? null)) {
            return null;
        }

        $code = mb_strtoupper(trim($item['value']));

        return $code === '' || preg_match('/^[A-Z0-9_-]{1,40}$/', $code) === 1 ? ['value' => $code] : null;
    }

    /** @param  list<string>  $allowed */
    private function choice(array $item, array $allowed): ?array
    {
        return is_string($item['value'] ?? null) && in_array(trim($item['value']), $allowed, true) ? ['value' => trim($item['value'])] : null;
    }

    private function flag(array $item): ?array
    {
        $value = $item['value'] ?? null;
        if (! is_bool($value) && ! is_string($value) && ! is_int($value)) {
            return null;
        }

        $flag = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        return $flag === null ? null : ['value' => $flag];
    }

    /** A money amount or percent as a number ("50k" and "1,5tr" are understood); "" clears the field when it may be empty. */
    private function amount(array $item, bool $clearable): ?array
    {
        $raw = $item['value'] ?? null;
        if ($clearable && is_string($raw) && trim($raw) === '') {
            return ['value' => ''];
        }

        $number = $this->number($raw);

        return $number === null || $number < 0 ? null : ['value' => $number == floor($number) ? (int) $number : round($number, 2)];
    }

    private function count(array $item): ?array
    {
        $raw = $item['value'] ?? null;
        if (is_string($raw) && trim($raw) === '') {
            return ['value' => ''];
        }

        $number = $this->number($raw);

        return $number === null || $number < 1 ? null : ['value' => (int) round($number)];
    }

    /** The form's datetime-local format, "YYYY-MM-DDTHH:MM"; a date alone means 00:00, or 23:59 for an end. */
    private function moment_(array $item, string $label, bool $endOfDay): ?array
    {
        try {
            $moment = $this->moment($item['value'] ?? null, $label, $endOfDay);
        } catch (InvalidActionException) {
            return null;
        }

        return ['value' => str_replace(' ', 'T', substr($moment, 0, 16))];
    }

    /**
     * Brands and products by name, as edits of the picked list like the sizes
     * and colors of a product: value (the whole list, [] clears it), add, remove.
     */
    private function names(array $item): ?array
    {
        $entry = [];
        foreach (['value', 'add', 'remove'] as $op) {
            if (is_array($item[$op] ?? null)) {
                $entry[$op] = $this->strings($item[$op]);
            }
        }

        return $entry === [] ? null : $entry;
    }
}
