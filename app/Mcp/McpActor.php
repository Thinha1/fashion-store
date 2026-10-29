<?php

namespace App\Mcp;

use App\Models\McpToken;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * The account a write token acts as. Order history, goods receipts and the
 * audit log record who did something, so each role gets one system account
 * ("Claude (MCP) · {role}") holding exactly that role's permissions. It has
 * no usable password, is left out of the staff list and does not count as a
 * remaining permission manager (see User::STATUS_SYSTEM).
 */
class McpActor
{
    public static function for(McpToken $token): User
    {
        $role = $token->role;
        $email = 'mcp-'.$role->code.'@fashion-store.invalid';

        $actor = User::query()->where('email', $email)->first();

        if ($actor === null) {
            $actor = new User([
                'name' => 'Claude (MCP) · '.$role->name,
                'email' => $email,
                'status' => User::STATUS_SYSTEM,
                'password' => Str::random(64),
            ]);
            $actor->role_id = $role->id;
            $actor->email_verified_at = now();
            $actor->save();
        }

        return $actor->setRelation('role', $role);
    }
}
