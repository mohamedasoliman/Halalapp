<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AllowLegacyCatalogue
{
    /**
     * Keep the legacy API available only during the app-store migration.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ((bool) config('mobile_api.legacy_catalogue_enabled', true)) {
            return $next($request);
        }

        return response()->json([
            'message' => 'This catalogue API has retired. Please update Halal Kiwi.',
            'minimum_version' => config('mobile_api.minimum_version'),
        ], 426);
    }
}
