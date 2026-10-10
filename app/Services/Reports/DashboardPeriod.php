<?php

namespace App\Services\Reports;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Database\Query\Builder as BuilderContract;

/**
 * The time window the admin dashboard reports on, picked from a fixed set of
 * presets (`?khoang=`). Every number on the page is computed against the same
 * window so they always agree; "Toàn thời gian" has no start and no
 * comparison period.
 */
final readonly class DashboardPeriod
{
    public const PRESETS = [
        'hom-nay' => 'Hôm nay',
        '7-ngay' => '7 ngày',
        '30-ngay' => '30 ngày',
        '90-ngay' => '90 ngày',
        'tat-ca' => 'Toàn thời gian',
    ];

    public const DEFAULT = '30-ngay';

    /**
     * How many days each preset spans — also how far back the comparison
     * window is shifted.
     */
    private const SPAN_DAYS = ['hom-nay' => 1, '7-ngay' => 7, '30-ngay' => 30, '90-ngay' => 90];

    public function __construct(
        public string $key,
        public ?CarbonImmutable $start,
        public CarbonImmutable $end,
    ) {}

    public static function fromKey(?string $key, ?CarbonImmutable $now = null): self
    {
        $now ??= CarbonImmutable::now();
        $key = array_key_exists((string) $key, self::PRESETS) ? (string) $key : self::DEFAULT;
        $days = self::SPAN_DAYS[$key] ?? null;

        return new self($key, $days === null ? null : $now->subDays($days - 1)->startOfDay(), $now);
    }

    public function label(): string
    {
        return self::PRESETS[$this->key];
    }

    /**
     * The same-length window just before this one, ending at the same clock
     * time (so "today so far" is compared with "yesterday up to now").
     */
    public function previous(): ?self
    {
        $days = self::SPAN_DAYS[$this->key] ?? null;

        if ($days === null || $this->start === null) {
            return null;
        }

        return new self($this->key, $this->start->subDays($days), $this->end->subDays($days));
    }

    public function comparisonLabel(): string
    {
        return match ($this->key) {
            'hom-nay' => 'so với hôm qua',
            default => 'so với '.mb_strtolower($this->label()).' trước',
        };
    }

    /**
     * Bucket size for the sales-over-time chart.
     *
     * @return 'hour'|'day'|'month'
     */
    public function granularity(): string
    {
        return match ($this->key) {
            'hom-nay' => 'hour',
            'tat-ca' => 'month',
            default => 'day',
        };
    }

    /**
     * Limit a query to this window on the given timestamp column.
     *
     * @template TQuery of BuilderContract
     *
     * @param  TQuery  $query
     * @return TQuery
     */
    public function apply(BuilderContract $query, string $column = 'placed_at'): BuilderContract
    {
        if ($this->start !== null) {
            $query->where($column, '>=', $this->start);
        }

        return $query->where($column, '<=', $this->end);
    }
}
