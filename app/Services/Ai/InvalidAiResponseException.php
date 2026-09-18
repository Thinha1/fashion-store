<?php

namespace App\Services\Ai;

use RuntimeException;

/**
 * The AI provider replied, but the text couldn't be parsed into the
 * product-draft JSON shape the widget expects.
 */
class InvalidAiResponseException extends RuntimeException {}
