<?php

namespace App\Policies;

use App\Models\CartItem;
use App\Models\User;

/**
 * Only the owner can change lines of their still-active cart.
 */
class CartItemPolicy
{
    public function update(User $user, CartItem $cartItem): bool
    {
        return $this->ownsActiveCart($user, $cartItem);
    }

    public function delete(User $user, CartItem $cartItem): bool
    {
        return $this->ownsActiveCart($user, $cartItem);
    }

    private function ownsActiveCart(User $user, CartItem $cartItem): bool
    {
        return $cartItem->cart->user_id === $user->id && $cartItem->cart->status === 'active';
    }
}
