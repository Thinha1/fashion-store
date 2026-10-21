<?php

namespace App\Services\Catalog;

use Illuminate\Http\Request;

/**
 * What a shopper asked the catalog for, read from the query string so every
 * search and filter combination is a plain, shareable URL. Also builds the
 * query strings for the filter chips (toggle one value, keep the rest).
 */
final readonly class CatalogFilters
{
    public const SORTS = ['lien-quan', 'moi-nhat', 'gia-tang', 'gia-giam'];

    /**
     * Price chips, in VND. The bounds are inclusive.
     *
     * @var list<array{label: string, min: int|null, max: int|null}>
     */
    public const PRICE_RANGES = [
        ['label' => 'Dưới 300.000đ', 'min' => null, 'max' => 300000],
        ['label' => '300.000đ – 500.000đ', 'min' => 300000, 'max' => 500000],
        ['label' => '500.000đ – 1.000.000đ', 'min' => 500000, 'max' => 1000000],
        ['label' => 'Trên 1.000.000đ', 'min' => 1000000, 'max' => null],
    ];

    private const MAX_QUERY_LENGTH = 100;

    private const MAX_VALUES = 10;

    /**
     * @param  list<string>  $sizes
     * @param  list<string>  $colors
     */
    public function __construct(
        public string $q = '',
        public string $category = '',
        public string $brand = '',
        public array $sizes = [],
        public array $colors = [],
        public ?int $priceMin = null,
        public ?int $priceMax = null,
        public bool $inStock = false,
        public string $sort = 'moi-nhat',
    ) {}

    public static function fromRequest(Request $request): self
    {
        $q = mb_substr(trim(preg_replace('/\s+/u', ' ', self::text($request->query('q')))), 0, self::MAX_QUERY_LENGTH);
        $sort = self::text($request->query('sort'));
        $sort = in_array($sort, self::SORTS, true) ? $sort : self::defaultSort($q);
        if ($sort === 'lien-quan' && $q === '') {
            $sort = 'moi-nhat';
        }

        $priceMin = self::money($request->query('price_min'));
        $priceMax = self::money($request->query('price_max'));
        if ($priceMin !== null && $priceMax !== null && $priceMin > $priceMax) {
            [$priceMin, $priceMax] = [$priceMax, $priceMin];
        }

        return new self(
            q: $q,
            category: self::text($request->query('category')),
            brand: self::text($request->query('brand')),
            sizes: self::list($request->query('size')),
            colors: self::list($request->query('color')),
            priceMin: $priceMin,
            priceMax: $priceMax,
            inStock: $request->boolean('in_stock'),
            sort: $sort,
        );
    }

    public function hasPriceFilter(): bool
    {
        return $this->priceMin !== null || $this->priceMax !== null;
    }

    /**
     * Whether anything narrows the list (the sort alone does not).
     */
    public function isFiltered(): bool
    {
        return $this->q !== '' || $this->category !== '' || $this->brand !== ''
            || $this->sizes !== [] || $this->colors !== [] || $this->hasPriceFilter() || $this->inStock;
    }

    /**
     * Query string for the current state with some keys replaced; a null,
     * empty or default value drops the key so URLs stay short.
     *
     * @param  array<string, mixed>  $changes
     * @return array<string, mixed>
     */
    public function query(array $changes = []): array
    {
        $query = array_merge([
            'q' => $this->q,
            'category' => $this->category,
            'brand' => $this->brand,
            'size' => $this->sizes,
            'color' => $this->colors,
            'price_min' => $this->priceMin,
            'price_max' => $this->priceMax,
            'in_stock' => $this->inStock ? 1 : null,
            'sort' => $this->sort,
        ], $changes);

        if ($query['sort'] === self::defaultSort((string) $query['q'])) {
            $query['sort'] = null;
        }

        return array_filter($query, fn ($value) => $value !== null && $value !== '' && $value !== []);
    }

    /**
     * Query string with one size/color value switched on or off.
     *
     * @param  'size'|'color'  $key
     * @return array<string, mixed>
     */
    public function toggle(string $key, string $value): array
    {
        $current = $key === 'size' ? $this->sizes : $this->colors;
        $next = in_array($value, $current, true)
            ? array_values(array_diff($current, [$value]))
            : [...$current, $value];

        return $this->query([$key => $next]);
    }

    /**
     * @param  array{min: int|null, max: int|null}  $range
     */
    public function isPriceRange(array $range): bool
    {
        return $this->priceMin === $range['min'] && $this->priceMax === $range['max'];
    }

    private static function defaultSort(string $q): string
    {
        return $q === '' ? 'moi-nhat' : 'lien-quan';
    }

    private static function text(mixed $value): string
    {
        return is_string($value) ? trim($value) : '';
    }

    /**
     * Accepts `size=M` as well as `size[]=M&size[]=L`.
     *
     * @return list<string>
     */
    private static function list(mixed $value): array
    {
        $values = array_map(self::text(...), is_array($value) ? $value : [$value]);

        return array_slice(array_values(array_unique(array_filter($values, fn (string $item) => $item !== ''))), 0, self::MAX_VALUES);
    }

    private static function money(mixed $value): ?int
    {
        if (! is_scalar($value)) {
            return null;
        }

        $digits = preg_replace('/\D/', '', (string) $value);

        return $digits !== '' && strlen($digits) <= 12 ? (int) $digits : null;
    }
}
