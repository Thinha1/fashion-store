<?php

namespace App\Services\Ai\Actions;

use App\Models\User;

/**
 * Something the admin assistant may do on the staff member's behalf, beyond
 * filling the product form: create vouchers, discount many variants, confirm
 * orders, draft goods receipts. The model only proposes; the staff member
 * sees the preview and confirms; execute() then re-checks everything against
 * the current data and does it through the app's own rules.
 */
interface AgentAction
{
    /** The "type" the model writes in an `actions` entry. */
    public function type(): string;

    /** Permission code a staff member needs for this action to be offered at all. */
    public function permission(): string;

    /** How to ask for this action, appended to the system prompt. */
    public function promptSpec(): string;

    /**
     * Normalize and check the model's parameters and show what would happen.
     *
     * @param  array<string, mixed>  $params
     *
     * @throws InvalidActionException when nothing sensible can be done; the message is shown in the chat
     */
    public function preview(array $params, User $actor): ActionPreview;

    /**
     * Do it, after the staff member confirmed the preview of these (already
     * normalized) parameters.
     *
     * @param  array<string, mixed>  $params
     *
     * @throws InvalidActionException
     */
    public function execute(array $params, User $actor): ActionResult;
}
