<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class apimiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = $request->header('X-API-Key');//api key

        $envappkey = config('app.key');//env app key

        //condition
        if ($apiKey !== $envappkey) {
            return response()->json(['message' => 'Please send an email to admin@halalkiwi.com to request the data :)'], 401);
        }

        return $next($request);
    }
}
