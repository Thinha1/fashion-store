<?php

namespace App\Support;

/**
 * Colour names are free text on product variants, so the storefront maps the
 * common ones to a display swatch in one place — the product card dots and the
 * detail-page swatches must agree on what "Kem" looks like.
 */
class ColorSwatches
{
    private const HEX = [
        'Đen' => '#17343a',
        'Xám đậm' => '#4b5563',
        'Xám' => '#8a9498',
        'Xanh navy' => '#1f2a44',
        'Xanh dương' => '#2563eb',
        'Xanh lá' => '#2f8f6b',
        'Xanh rêu' => '#5b6b49',
        'Tím than' => '#3b3a66',
        'Trắng' => '#ffffff',
        'Trắng họa tiết' => '#eef0ee',
        'Kem' => '#efe3cf',
        'Be' => '#d9c7a9',
        'Nâu' => '#5c4033',
        'Nâu nhạt' => '#b89b79',
        'Đỏ' => '#dc2626',
    ];

    public static function hex(string $color): string
    {
        return self::HEX[$color] ?? '#e5e7eb';
    }
}
