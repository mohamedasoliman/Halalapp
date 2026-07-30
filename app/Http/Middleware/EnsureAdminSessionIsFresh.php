<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminSessionIsFresh
{
    public function handle(Request $request, Closure $next): Response
    {
        $lastActivity = (int) $request->session()->get('admin_last_activity', 0);
        $idleTimeout = max(5, (int) config('admin_security.idle_timeout_minutes', 30)) * 60;

        if ($lastActivity > 0 && (now()->timestamp - $lastActivity) > $idleTimeout) {
            Auth::guard('admin')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('admin.login')
                ->with('error', 'Your administrator session expired due to inactivity.');
        }

        $request->session()->put('admin_last_activity', now()->timestamp);

        return $next($request);
    }
}
