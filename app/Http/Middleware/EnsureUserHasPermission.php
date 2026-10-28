<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasPermission
{
    /**
     * Handle an incoming request.
     *
     * Aborts with 403 unless the authenticated user's role carries the given
     * permission code. Must run after the `auth` middleware.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        // Locked while signed in: end the session instead of a bare 403.
        if ($request->user() !== null && ! $request->user()->isActive()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['email' => 'Tài khoản đã bị khóa. Vui lòng liên hệ quản trị viên.']);
        }

        abort_unless($request->user()?->hasPermission($permission), 403);

        return $next($request);
    }
}
