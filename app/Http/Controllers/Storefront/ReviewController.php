<?php

namespace App\Http\Controllers\Storefront;

use App\Actions\ReviewNotAllowedException;
use App\Actions\SubmitReview;
use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\SubmitReviewRequest;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ReviewController extends Controller
{
    /**
     * "Sản phẩm đã mua": the lines of the customer's delivered orders, split
     * into those still waiting for a review and those already reviewed.
     */
    public function index(Request $request): View
    {
        $customer = $request->user();
        $tab = $request->query('tab') === 'da-danh-gia' ? 'done' : 'pending';

        $pending = OrderItem::query()
            ->whereDoesntHave('review')
            ->whereHas('order', fn ($order) => $order->where('user_id', $customer->id)->where('status', 'delivered'));

        return view('storefront.reviews.index', [
            'tab' => $tab,
            'pendingCount' => (clone $pending)->count(),
            'doneCount' => $customer->reviews()->count(),
            'items' => $tab === 'pending'
                ? $pending->with(['order', 'productVariant.product.images' => fn ($images) => $images->where('is_primary', true)])
                    ->latest('id')->paginate(10)->withQueryString()
                : null,
            'reviews' => $tab === 'done'
                ? $customer->reviews()->with(['product', 'orderItem:id,product_name'])->latest('id')->paginate(10)->withQueryString()
                : null,
        ]);
    }

    /**
     * Rate one line of the customer's own delivered order, from the
     * "Sản phẩm đã mua" page; goes back to wherever the form was sent from.
     */
    public function store(SubmitReviewRequest $request, Order $order, OrderItem $orderItem, SubmitReview $submitReview): RedirectResponse
    {
        Gate::authorize('view', $order);
        abort_unless($orderItem->order_id === $order->id, 404);

        try {
            $submitReview->execute($orderItem, $request->user(), (int) $request->validated('rating'), $request->validated('comment'));
        } catch (ReviewNotAllowedException $exception) {
            return redirect()->back(fallback: route('orders.show', $order))->with('error', $exception->getMessage());
        }

        return redirect()->back(fallback: route('orders.show', $order))->with('status', 'Cảm ơn bạn đã đánh giá. Đánh giá sẽ hiển thị sau khi cửa hàng duyệt.');
    }
}
