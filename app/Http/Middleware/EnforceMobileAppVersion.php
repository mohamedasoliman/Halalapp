<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceMobileAppVersion
{
    /**
     * Reject unsupported mobile releases after the version policy is enabled.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $minimumVersion = trim((string) config('mobile_api.minimum_version', ''));
        $requiresVersion = (bool) config('mobile_api.require_version', false);

        if (! $requiresVersion || $minimumVersion === '') {
            return $next($request);
        }

        $appVersion = trim((string) $request->header('X-App-Version', ''));
        if (
            $appVersion === ''
            || ! preg_match('/^\d+\.\d+\.\d+$/', $appVersion)
            || version_compare($appVersion, $minimumVersion, '<')
        ) {
            return response()->json([
                'message' => 'This version of Halal Kiwi is no longer supported. Please update the app.',
                'minimum_version' => $minimumVersion,
            ], 426);
        }

        $response = $next($request);
        $response->headers->set('X-Minimum-App-Version', $minimumVersion);

        return $response;
    }
}
