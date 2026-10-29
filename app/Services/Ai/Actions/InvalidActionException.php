<?php

namespace App\Services\Ai\Actions;

use RuntimeException;

/**
 * A proposed or confirmed action that cannot be carried out; the message is
 * written for the staff member and shown in the chat.
 */
class InvalidActionException extends RuntimeException {}
