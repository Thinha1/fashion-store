<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Services\Catalog\HomeShelves;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HomeShelfController extends Controller
{
    /**
     * The next four products of a home-page shelf, as rendered cards for the
     * carousel to append, plus the URL of the page after (null at the end).
     */
    public function __invoke(Request $request, HomeShelves $shelves, string $shelf): JsonResponse
    {
        $except = collect(explode(',', (string) $request->query('except')))
            ->map(fn (string $id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->take(12)
            ->values()
            ->all();

        $page = $shelves->page($shelf, $except, (int) $request->query('page', 1));

        return response()->json([
            'html' => view('storefront.home._shelf-items', ['products' => $page->items()])->render(),
            'next' => $page->hasMorePages()
                ? route('home.shelf', array_filter(['shelf' => $shelf, 'page' => $page->currentPage() + 1, 'except' => implode(',', $except)]))
                : null,
        ]);
    }
}
