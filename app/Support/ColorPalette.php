<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * The fixed set of variant colors. Admins pick from this list (product form,
 * Excel import), so color names stay consistent for the catalog filter, and
 * every name has a swatch color for the product page.
 */
final class ColorPalette
{
    /**
     * Name => swatch hex, grouped roughly by hue.
     *
     * @var array<string, string>
     */
    public const COLORS = [
        'Đen' => '#1a1a1a',
        'Trắng' => '#ffffff',
        'Trắng ngà' => '#f5f0e6',
        'Kem' => '#efe4cf',
        'Be' => '#d8c3a5',
        'Be kaki' => '#b9a27a',
        'Nâu nhạt' => '#a67b5b',
        'Nâu' => '#6f4e37',
        'Xám nhạt' => '#d4d4d8',
        'Xám' => '#9ca3af',
        'Xám đậm' => '#4b5563',
        'Xanh nhạt' => '#a5c8e4',
        'Xanh da trời' => '#7cb9e8',
        'Xanh dương' => '#2563eb',
        'Xanh dương đậm' => '#1e3a8a',
        'Xanh navy' => '#1e2a4a',
        'Xanh lá nhạt' => '#a3c9a8',
        'Xanh lá' => '#2f855a',
        'Xanh rêu' => '#4b5320',
        'Vàng' => '#facc15',
        'Vàng mustard' => '#d4a017',
        'Cam' => '#f97316',
        'Đỏ' => '#dc2626',
        'Đỏ đô' => '#7f1d1d',
        'Hồng phấn' => '#f8c8d4',
        'Hồng' => '#ec7aa6',
        'Tím nhạt' => '#c4b5fd',
        'Tím' => '#7c3aed',
    ];

    /**
     * Other ways people (and the AI assistant) name these colors, keyed by
     * the ascii slug of the alias.
     *
     * @var array<string, string>
     */
    private const ALIASES = [
        'black' => 'Đen', 'den-tuyen' => 'Đen',
        'white' => 'Trắng', 'trang-tinh' => 'Trắng',
        'off-white' => 'Trắng ngà', 'ivory' => 'Trắng ngà', 'cream' => 'Kem',
        'beige' => 'Be', 'khaki' => 'Be kaki', 'kaki' => 'Be kaki',
        'brown' => 'Nâu', 'grey' => 'Xám', 'gray' => 'Xám', 'ghi' => 'Xám',
        'xam-sang' => 'Xám nhạt', 'light-grey' => 'Xám nhạt', 'dark-grey' => 'Xám đậm', 'xam-than' => 'Xám đậm',
        'xanh' => 'Xanh dương', 'blue' => 'Xanh dương', 'xanh-bien' => 'Xanh dương',
        'light-blue' => 'Xanh nhạt', 'sky-blue' => 'Xanh da trời', 'navy' => 'Xanh navy', 'xanh-tim-than' => 'Xanh navy',
        'green' => 'Xanh lá', 'xanh-la-cay' => 'Xanh lá', 'olive' => 'Xanh rêu',
        'yellow' => 'Vàng', 'mustard' => 'Vàng mustard', 'orange' => 'Cam',
        'red' => 'Đỏ', 'burgundy' => 'Đỏ đô', 'do-man' => 'Đỏ đô',
        'pink' => 'Hồng', 'hong-nhat' => 'Hồng phấn', 'purple' => 'Tím', 'tim-pastel' => 'Tím nhạt',
    ];

    private const FALLBACK_HEX = '#e5e7eb';

    /**
     * @return list<string>
     */
    public static function names(): array
    {
        return array_keys(self::COLORS);
    }

    public static function hex(?string $name): string
    {
        return self::COLORS[$name ?? ''] ?? self::FALLBACK_HEX;
    }

    /**
     * The palette name a free-text color refers to (case, diacritics and a
     * few English/colloquial names forgiven), or null when it matches none.
     */
    public static function match(?string $value): ?string
    {
        $slug = Str::slug((string) $value);
        if ($slug === '') {
            return null;
        }

        foreach (self::names() as $name) {
            if (Str::slug($name) === $slug) {
                return $name;
            }
        }

        return self::ALIASES[$slug] ?? null;
    }
}
