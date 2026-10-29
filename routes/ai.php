<?php

use App\Http\Middleware\AuthenticateMcpToken;
use App\Mcp\Servers\ShopServer;
use Laravel\Mcp\Facades\Mcp;

// MCP server for staff asking from Claude (Claude Code / Claude Desktop).
// Not under /mcp: on the Tailscale Funnel that prefix belongs to another
// server. Tokens come from `php artisan mcp:token {role}`.
Mcp::web('/ai/shop', ShopServer::class)
    ->middleware([AuthenticateMcpToken::class, 'throttle:mcp'])
    ->name('mcp.shop');
