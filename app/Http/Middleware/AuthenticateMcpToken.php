<?php

namespace App\Http\Middleware;

use App\Models\McpToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the MCP endpoint: the request must carry `Authorization: Bearer
 * <token>` for a usable McpToken, which the tools then read through
 * McpToken::current().
 */
class AuthenticateMcpToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = McpToken::findUsable($request->bearerToken());

        if ($token === null) {
            // laravel/mcp's AddWwwAuthenticateHeader adds the Bearer challenge.
            return response()->json(['error' => 'Token MCP không hợp lệ, đã hết hạn hoặc đã bị thu hồi.'], 401);
        }

        // One write a minute is enough to show a token is in use.
        if ($token->last_used_at === null || $token->last_used_at->lt(now()->subMinute())) {
            $token->forceFill(['last_used_at' => now()])->saveQuietly();
        }

        app()->instance(McpToken::CURRENT, $token);

        return $next($request);
    }
}
