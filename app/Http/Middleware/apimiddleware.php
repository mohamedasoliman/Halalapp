<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class apimiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = (string) $request->header('X-API-Key', '');
        $expectedKey = (string) config('app.api_key', '');

        if ($expectedKey === '') {
            Log::critical('Mobile API rejected a request because API_KEY is not configured.');

            return response()->json(['message' => 'Service unavailable.'], 503);
        }

        if ($apiKey === '' || ! hash_equals($expectedKey, $apiKey)) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        return $next($request);
    }
}
