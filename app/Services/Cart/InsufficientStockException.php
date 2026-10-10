<?php

namespace App\Services\Cart;

/**
 * Raised by PlaceOrder when, under row locks, some cart lines can no longer
 * be fulfilled. The whole order is rolled back.
 */
class InsufficientStockException extends CartException
{
    /**
     * @param  list<string>  $problems  one line per affected cart line
     */
    public static function forLines(array $problems): self
    {
        return new self('Không thể đặt hàng: '.implode(' ', $problems));
    }
}
