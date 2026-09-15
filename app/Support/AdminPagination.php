<?php

namespace App\Support;

use Illuminate\Http\Request;

class AdminPagination
{
    public const PAGE_SIZES = [10, 20, 50];

    public static function perPage(Request $request): int
    {
        $perPage = filter_var($request->query('per_page'), FILTER_VALIDATE_INT);

        return in_array($perPage, self::PAGE_SIZES, true) ? $perPage : 20;
    }
}
