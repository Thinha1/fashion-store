<?php

namespace App\Actions;

use RuntimeException;

/**
 * An order status change that isn't allowed from where the order is now; the
 * message is shown to whoever asked for it.
 */
class OrderStatusException extends RuntimeException {}
