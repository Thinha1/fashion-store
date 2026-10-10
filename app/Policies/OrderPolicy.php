<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

/**
 * Customers only see their own orders. Staff access goes through the admin
 * permission middleware, not this policy.
 */
class OrderPolicy
{
    public function view(User $user, Order $order): bool
    {
        return $order->user_id === $user->id;
    }
}
