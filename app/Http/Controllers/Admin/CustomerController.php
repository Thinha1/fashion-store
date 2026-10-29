<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use App\Services\Reports\CustomerStats;
use App\Support\AdminPagination;
use App\Support\AdminSorting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Customers, read-only: the accounts with the "customer" role, how much they
 * ordered and spent, and for one customer their addresses and order history.
 * "Spent" counts delivered and paid orders only, like the dashboard's revenue.
 */
class CustomerController extends Controller
{
    private const FILTERS = ['bought' => 'Đã từng mua', 'never' => 'Chưa mua'];

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $filter = is_string($request->query('mua')) && array_key_exists($request->query('mua'), self::FILTERS) ? $request->query('mua') : null;

        $sorting = new AdminSorting($request, [
            'name' => 'name', 'orders_count' => 'orders_count', 'spent' => 'spent',
            'last_order_at' => 'last_order_at', 'created_at' => 'created_at',
        ]);

        $customers = $sorting->apply(CustomerStats::customers()
            ->when($search !== '', function (Builder $query) use ($search): void {
                $like = '%'.addcslashes($search, '%_\\').'%';

                $query->where(fn (Builder $query) => $query
                    ->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhereHas('addresses', fn (Builder $addresses) => $addresses->where('phone', 'like', $like)));
            })
            ->when($filter === 'bought', fn (Builder $query) => $query->has('orders'))
            ->when($filter === 'never', fn (Builder $query) => $query->doesntHave('orders'))
            ->orderByDesc('created_at')
            ->orderByDesc('id'))
            ->paginate(AdminPagination::perPage($request))->withQueryString();

        return view('admin.customers.index', [
            'customers' => $customers,
            'sorting' => $sorting,
            'search' => $search,
            'filter' => $filter,
            'filters' => self::FILTERS,
        ]);
    }

    public function show(User $customer): View
    {
        abort_unless($customer->role?->code === 'customer', 404);

        $spent = Order::query()->where('user_id', $customer->id)->tap(CustomerStats::spent(...));

        return view('admin.customers.show', [
            'customer' => $customer,
            'addresses' => $customer->addresses()->orderByDesc('is_default')->orderBy('id')->get(),
            'orders' => $customer->orders()->withCount('items')->orderByDesc('placed_at')->orderByDesc('id')
                ->paginate(10, pageName: 'don')->withQueryString(),
            'stats' => [
                'orders' => $customer->orders()->count(),
                'spent' => (float) (clone $spent)->sum('grand_total'),
                'average' => (float) (clone $spent)->avg('grand_total'),
                'last_order_at' => $customer->orders()->max('placed_at'),
            ],
        ]);
    }
}
