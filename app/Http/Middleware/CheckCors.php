<?php

namespace App\Http\Middleware;

// use App\Http\Controllers\Admin\AdminController;
// use Admin\AdminController;

use Closure;
use Illuminate\Http\Request;

class CheckCors
{
    /**
     * Handle an incoming request.
     *
     * @param  Request  $request
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        return $next($request)
            // ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'POST, GET, OPTIONS, PUT, DELETE')
            ->header('Access-Control-Max-Age', '86400')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, x-api-key');

    }
}
