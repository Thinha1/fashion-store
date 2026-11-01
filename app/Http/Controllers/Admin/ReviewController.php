<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Services\Audit\AuditLogger;
use App\Support\AdminPagination;
use App\Support\AdminSorting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Staff moderate product reviews: a customer's review waits as `pending`
 * until staff approve it, and staff hide the ones that break the rules (and
 * can show them again). The text and the stars are never edited.
 */
class ReviewController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $status = is_string($request->query('trang-thai')) && array_key_exists($request->query('trang-thai'), Review::STATUS_LABELS) ? $request->query('trang-thai') : null;
        $rating = in_array((int) $request->query('sao'), range(Review::MIN_RATING, Review::MAX_RATING), true) ? (int) $request->query('sao') : null;

        $sorting = new AdminSorting($request, ['rating' => 'rating', 'created_at' => 'created_at']);

        $reviews = $sorting->apply(Review::query()
            ->with(['product:id,name,slug', 'user:id,name,email', 'orderItem:id,color_name,size_name', 'updatedBy:id,name'])
            ->when($search !== '', function (Builder $query) use ($search): void {
                $like = '%'.addcslashes($search, '%_\\').'%';

                $query->where(fn (Builder $query) => $query
                    ->where('comment', 'like', $like)
                    ->orWhereHas('product', fn (Builder $product) => $product->where('name', 'like', $like))
                    ->orWhereHas('user', fn (Builder $user) => $user->where('name', 'like', $like)->orWhere('email', 'like', $like)));
            })
            ->when($status, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($rating, fn (Builder $query, int $rating) => $query->where('rating', $rating))
            ->orderByDesc('created_at')
            ->orderByDesc('id'))
            ->paginate(AdminPagination::perPage($request))->withQueryString();

        return view('admin.reviews.index', [
            'reviews' => $reviews,
            'pendingCount' => Review::query()->where('status', Review::STATUS_PENDING)->count(),
            'sorting' => $sorting,
            'search' => $search,
            'status' => $status,
            'rating' => $rating,
        ]);
    }

    public function update(Request $request, Review $review): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in([Review::STATUS_PUBLISHED, Review::STATUS_HIDDEN])],
        ]);

        $from = $review->status;
        $action = match (true) {
            $data['status'] === Review::STATUS_HIDDEN => 'review.hidden',
            $from === Review::STATUS_PENDING => 'review.approved',
            default => 'review.published',
        };

        if ($from !== $data['status']) {
            $review->forceFill(['status' => $data['status'], 'updated_by' => $request->user()->id])->save();

            app(AuditLogger::class)->record($action, $review, ['status' => $from], ['status' => $data['status']]);
        }

        return back()->with('status', match ($action) {
            'review.hidden' => 'Đã ẩn đánh giá.',
            'review.approved' => 'Đã duyệt đánh giá, đánh giá đã hiện trên trang sản phẩm.',
            default => 'Đã hiển thị lại đánh giá.',
        });
    }
}
