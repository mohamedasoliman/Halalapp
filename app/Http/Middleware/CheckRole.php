<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    public function handle(Request $request, Closure $next, string $permissionIds): Response
    {
        $admin = Auth::guard('admin')->user();
        $allowedRoleIds = array_map('intval', explode(',', $permissionIds));

        if (! $admin || ! in_array((int) $admin->role_id, $allowedRoleIds, true)) {
            abort(403, 'You do not have permission to perform this action.');
        }

        return $next($request);
    }
}
