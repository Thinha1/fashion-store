<?php

namespace App\Services\Cart;

use RuntimeException;

/**
 * A cart change that can't be made; the message is shown to the customer.
 */
class CartException extends RuntimeException {}
