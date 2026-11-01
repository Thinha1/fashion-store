<?php

namespace App\Actions;

use RuntimeException;

/**
 * The customer may not review this order line; the message is customer-facing.
 */
class ReviewNotAllowedException extends RuntimeException {}
