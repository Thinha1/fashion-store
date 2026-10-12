<?php

namespace App\Http\Controllers\Admin;

use App\Actions\ChangeOrderStatus;
use App\Actions\ConfirmTransferPayment;
use App\Actions\OrderStatusException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateOrderStatusRequest;
use App\Models\Order;
use App\Models\User;
use App\Support\AdminPagination;
use App\Support\AdminSorting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderController extends Controller
{
    /**
     * All orders, newest first, with a status tab filter, payment status /
     * payment method filters and a search over order number / customer
     * name / phone / email. The tab counts follow the payment filters.
     */
    public function index(Request $request): View
    {
        $status = $this->allowedValue($request->query('status'), Order::STATUS_LABELS);
        $paymentStatus = $this->allowedValue($request->query('payment_status'), Order::PAYMENT_STATUS_LABELS);
        $paymentMethod = $this->allowedValue($request->query('payment_method'), Order::PAYMENT_METHOD_LABELS);
        $search = trim((string) $request->query('q', ''));
        $paymentFilters = fn (Builder $query) => $query
            ->when($paymentStatus, fn (Builder $query) => $query->where('payment_status', $paymentStatus))
            ->when($paymentMethod, fn (Builder $query) => $query->where('payment_method', $paymentMethod));

        $sorting = new AdminSorting($request, [
            'order_number' => 'order_number', 'customer' => 'customer_name', 'placed_at' => 'placed_at',
            'grand_total' => 'grand_total', 'status' => 'status',
        ]);

        $orders = $sorting->apply(Order::query()
            ->withCount('items')
            ->tap($paymentFilters)
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($search !== '', function ($query) use ($search): void {
                $like = '%'.addcslashes($search, '%_\\').'%';

                $query->where(fn ($query) => $query
                    ->where('order_number', 'like', $like)
                    ->orWhere('customer_name', 'like', $like)
                    ->orWhere('customer_phone', 'like', $like)
                    ->orWhere('customer_email', 'like', $like));
            })
            ->orderByDesc('placed_at')
            ->orderByDesc('id'))
            ->paginate(AdminPagination::perPage($request))->withQueryString();

        return view('admin.orders.index', [
            'orders' => $orders,
            'sorting' => $sorting,
            'status' => $status,
            'paymentStatus' => $paymentStatus,
            'paymentMethod' => $paymentMethod,
            'search' => $search,
            'statusCounts' => Order::query()->tap($paymentFilters)
                ->selectRaw('status, count(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status'),
        ]);
    }

    /**
     * A query-string value, or null unless it is one of the given keys.
     *
     * @param  array<string, string>  $labels
     */
    private function allowedValue(mixed $value, array $labels): ?string
    {
        return is_string($value) && array_key_exists($value, $labels) ? $value : null;
    }

    public function show(Order $order): View
    {
        $order->load(['items', 'user:id,name,email,phone', 'discount:id,code', 'updatedBy:id,name', 'paymentReviewer:id,name']);

        $actorIds = collect($order->status_history)->pluck('actor_id')->filter()->unique();

        return view('admin.orders.show', [
            'order' => $order,
            'actorNames' => User::query()->whereKey($actorIds)->pluck('name', 'id'),
        ]);
    }

    public function updateStatus(UpdateOrderStatusRequest $request, Order $order, ChangeOrderStatus $changeOrderStatus): RedirectResponse
    {
        $toStatus = $request->validated('status');

        try {
            $changeOrderStatus->execute($order, $toStatus, $request->user(), $request->validated('note'));
        } catch (OrderStatusException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('status', "Đơn {$order->order_number} đã chuyển sang \"".Order::STATUS_LABELS[$toStatus].'".');
    }

    /**
     * Staff saw the money in the shop's account but the SePay webhook didn't
     * match it (e.g. a changed transfer memo).
     */
    public function confirmPayment(Request $request, Order $order, ConfirmTransferPayment $confirmTransferPayment): RedirectResponse
    {
        try {
            $confirmTransferPayment->execute($order, $request->user());
        } catch (OrderStatusException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('status', "Đã xác nhận thanh toán cho đơn {$order->order_number}.");
    }
}
