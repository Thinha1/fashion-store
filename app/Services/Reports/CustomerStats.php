<?php

namespace App\Services\Reports;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * How the shop counts a customer's orders and money, shared by the admin
 * customer screens and the MCP customer tools so both always agree:
 *  - orders: every order, cancelled ones included;
 *  - spent: delivered AND paid orders only, like the dashboard's revenue.
 */
final class CustomerStats
{
    /**
     * The orders that count as money spent.
     *
     * @param  Builder<Order>  $orders
     */
    public static function spent(Builder $orders): void
    {
        $orders->where('status', 'delivered')->where('payment_status', 'paid');
    }

    /**
     * Customers with `orders_count`, `spent` (null when nothing delivered and
     * paid yet) and `last_order_at` attached.
     *
     * @return Builder<User>
     */
    public static function customers(): Builder
    {
        return User::query()
            ->customers()
            ->withCount('orders')
            ->withSum(['orders as spent' => fn (Builder $orders) => self::spent($orders)], 'grand_total')
            ->withMax('orders as last_order_at', 'placed_at');
    }
}
