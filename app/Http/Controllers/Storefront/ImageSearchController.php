<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\ImageSearchRequest;
use App\Services\Ai\ImageSearchDescriber;
use App\Services\Ai\InvalidAiResponseException;
use App\Services\Catalog\CatalogFilters;
use App\Services\Catalog\CatalogSearch;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * "Tìm bằng hình ảnh": the AI describes the shopper's photo, then the
 * shopper lands on the normal catalog page searching for it. Filters are
 * relaxed step by step (color first) until something matches, since the
 * AI's color name may differ from the one the shop uses.
 */
class ImageSearchController extends Controller
{
    private const NOT_RECOGNISED = 'Không nhận ra sản phẩm trong ảnh, bạn thử ảnh khác nhé.';

    private const UNAVAILABLE = 'Chưa thể tìm bằng ảnh lúc này. Vui lòng thử lại sau hoặc tìm bằng chữ.';

    public function __invoke(ImageSearchRequest $request, ImageSearchDescriber $describer, CatalogSearch $catalog): RedirectResponse
    {
        try {
            $found = $describer->describe($request->file('image'));
        } catch (InvalidAiResponseException $exception) {
            Log::info('Image search: photo not usable.', ['reason' => $exception->getMessage()]);

            return to_route('products.index')->with('error', self::NOT_RECOGNISED);
        } catch (ConnectionException|RuntimeException) {
            return to_route('products.index')->with('error', self::UNAVAILABLE);
        }

        $category = $found['category'] ?? '';
        $colors = $found['color'] ? [$found['color']] : [];
        $candidates = [
            new CatalogFilters(q: $found['keywords'], category: $category, colors: $colors, sort: 'lien-quan'),
            new CatalogFilters(q: $found['keywords'], category: $category, sort: 'lien-quan'),
            new CatalogFilters(q: $found['keywords'], sort: 'lien-quan'),
            new CatalogFilters(category: $category),
        ];

        foreach ($candidates as $filters) {
            if ($filters->isFiltered() && $catalog->paginate($filters, 1)->total() > 0) {
                return to_route('products.index', $filters->query());
            }
        }

        return to_route('products.index', $candidates[2]->query());
    }
}
