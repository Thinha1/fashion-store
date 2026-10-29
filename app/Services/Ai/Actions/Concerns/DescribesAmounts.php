<?php

namespace App\Services\Ai\Actions\Concerns;

use App\Services\Ai\Actions\InvalidActionException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Small helpers the actions share: reading the model's loose values and
 * writing amounts and dates the way the admin shows them.
 */
trait DescribesAmounts
{
    private function money(float|int|string|null $amount): string
    {
        return number_format((float) $amount, 0, ',', '.').' ₫';
    }

    private function percent(float|int|string $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 2, ',', '.'), '0'), ',').'%';
    }

    private function amount(string $type, float|int|string $value): string
    {
        return $type === 'percent' ? $this->percent($value) : $this->money($value);
    }

    private function dateTime(string $value): string
    {
        return CarbonImmutable::parse($value)->format('H:i d/m/Y');
    }

    /** A number the model wrote as 50000, "50000", "50.000" or "50k"; null when absent. */
    private function number(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $text = mb_strtolower(str_replace([' ', '₫', 'đ', 'vnd'], '', trim($value)));
        $multiplier = match (true) {
            str_ends_with($text, 'tr') => 1_000_000,
            str_ends_with($text, 'k') => 1_000,
            default => 1,
        };
        $digits = rtrim($text, 'trk');
        $digits = $multiplier > 1 ? str_replace(',', '.', $digits) : str_replace(['.', ','], ['', '.'], $digits);

        return is_numeric($digits) ? (float) $digits * $multiplier : null;
    }

    /**
     * "YYYY-MM-DD HH:MM" or "YYYY-MM-DD" (start of day for a start, end of
     * day for an end), as the request format the admin forms take.
     *
     * @throws InvalidActionException
     */
    private function moment(mixed $value, string $label, bool $endOfDay = false): string
    {
        if (! is_string($value) || trim($value) === '') {
            throw new InvalidActionException("Chưa có {$label}.");
        }

        try {
            $moment = CarbonImmutable::parse(trim($value));
        } catch (\Throwable) {
            throw new InvalidActionException("{$label} \"{$value}\" không đúng định dạng ngày giờ.");
        }

        if ($endOfDay && ! str_contains($value, ':')) {
            $moment = $moment->endOfDay()->startOfMinute();
        }

        return $moment->format('Y-m-d H:i:s');
    }

    /**
     * Records whose name equals `$name` ignoring case, compared in PHP so that
     * accented capitals ("Áo khoác") match on every database.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  class-string<TModel>  $model
     * @return Collection<int, TModel>
     */
    private function named(string $model, string $name): Collection
    {
        $wanted = mb_strtolower(trim($name));

        return $model::query()->get()->filter(fn ($record) => mb_strtolower(trim((string) $record->name)) === $wanted)->values();
    }

    /** @return list<string> */
    private function strings(mixed $value): array
    {
        return is_array($value)
            ? array_values(array_filter(array_map(fn ($item) => is_string($item) ? trim($item) : '', $value), fn ($item) => $item !== ''))
            : [];
    }
}
