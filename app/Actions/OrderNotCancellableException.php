<?php

namespace App\Actions;

/**
 * The order has moved past a cancellable status; the message is customer-facing.
 */
class OrderNotCancellableException extends OrderStatusException {}
