<?php

namespace App\Services\Pricing;

use RuntimeException;

/**
 * A coupon code that can't be applied; the message is shown to the customer.
 */
class InvalidCouponException extends RuntimeException {}
