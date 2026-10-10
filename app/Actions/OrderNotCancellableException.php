<?php

namespace App\Actions;

use RuntimeException;

/**
 * The order has moved past a cancellable status; the message is customer-facing.
 */
class OrderNotCancellableException extends RuntimeException {}
