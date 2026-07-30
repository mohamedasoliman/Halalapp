<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class LogSensitiveApiAccess
{
    /**
     * Record privacy-preserving telemetry for catalogue and barcode access.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = microtime(true);
        $requestId = (string) Str::uuid();
        $response = $next($request);

        $search = trim((string) $request->input('search', ''));
        $searchKind = match (true) {
            $search === '' => 'empty',
            ctype_digit($search) => 'numeric',
            default => 'text',
        };

        Log::channel('security')->info('mobile_api_access', [
            'request_id' => $requestId,
            'route' => $request->route()?->uri(),
            'method' => $request->method(),
            'status' => $response->getStatusCode(),
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'ip_hash' => $this->hashValue($request->ip()),
            'install_hash' => $this->hashValue($request->header('X-Install-ID')),
            'app_version' => Str::limit((string) $request->header('X-App-Version', 'unknown'), 20, ''),
            'app_build' => Str::limit((string) $request->header('X-App-Build', 'unknown'), 20, ''),
            'platform' => Str::limit((string) $request->header('X-App-Platform', 'unknown'), 20, ''),
            'app_check_status' => (string) $request->attributes->get('app_check_status', 'unknown'),
            'page' => max(1, (int) $request->input('page', 1)),
            'search_kind' => $searchKind,
            'search_hash' => $this->hashValue($search),
        ]);

        $response->headers->set('X-Request-ID', $requestId);
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }

    private function hashValue(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return hash_hmac('sha256', $value, (string) config('app.key'));
    }
}
