<?php

namespace App\Http\Middleware;

use Closure;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next, $permisionIds)
    {

        if(userRoleCheck(auth()->user()->role_id) == 'Admin'){
            return $next($request);
        }
        return redirect('admin')->with('error', 'Permission Denied!!! You do not have administrative access.');
    }
}
