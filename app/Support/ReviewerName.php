<?php

namespace App\Support;

/**
 * Reviews are public, so a customer's name is shown partly hidden: the first
 * word keeps its first letter and the last word stays ("Nguyễn Văn An" → "N*** An").
 */
class ReviewerName
{
    public static function mask(?string $name): string
    {
        $words = preg_split('/\s+/u', trim((string) $name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($words === []) {
            return 'Khách hàng';
        }

        $first = array_shift($words);
        $masked = mb_substr($first, 0, 1).'***';

        return $words === [] ? $masked : $masked.' '.end($words);
    }
}
