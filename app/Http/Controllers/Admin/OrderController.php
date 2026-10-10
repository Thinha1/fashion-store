<?php

namespace App\Http\Controllers\Admin;

use App\Actions\ChangeOrderStatus;
use App\Actions\OrderStatusException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateOrderStatusRequest;
use App\Models\Order;
use App\Models\User;
use App\Support\AdminPagination;
use App\Support\AdminSorting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderController extends Controller
{
    /**
     * All orders, newest first, with a status tab filter and a search over
     * order number / customer name / phone / email.
     */
    public function index(Request $request): View
    {
        $status = $request->query('status');
        $status = is_string($status) && array_key_exists($status, Order::STATUS_LABELS) ? $status : null;
        $search = trim((string) $request->query('q', ''));

        $sorting = new AdminSorting($request, [
            'order_number' => 'order_number', 'customer' => 'customer_name', 'placed_at' => 'placed_at',
            'grand_total' => 'grand_total', 'status' => 'status',
        ]);

        $orders = $sorting->apply(Order::query()
            ->withCount('items')
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
            'search' => $search,
            'statusCounts' => Order::query()->selectRaw('status, count(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status'),
        ]);
    }

    public function show(Order $order): View
    {
        $order->load(['items', 'user:id,name,email,phone', 'discount:id,code', 'updatedBy:id,name']);

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
}
