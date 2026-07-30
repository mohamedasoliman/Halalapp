<?php

namespace App\Http\Middleware;

use App\Services\Security\FirebaseAppCheckTokenVerifier;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class VerifyFirebaseAppCheck
{
    public function __construct(
        private readonly FirebaseAppCheckTokenVerifier $verifier
    ) {}

    /**
     * Verify Firebase App Check without disrupting old clients during monitor mode.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $mode = strtolower(trim((string) config('app_check.mode', 'off')));
        if (! in_array($mode, ['monitor', 'enforce'], true)) {
            $request->attributes->set('app_check_status', 'off');

            return $next($request);
        }

        $token = trim((string) $request->header('X-Firebase-AppCheck', ''));
        $status = 'missing';

        if ($token !== '') {
            try {
                $appId = $this->verifier->verify($token);
                $request->attributes->set('app_check_app_id', $appId);
                $status = 'valid';
            } catch (Throwable) {
                $status = 'invalid';
            }
        }

        $request->attributes->set('app_check_status', $status);

        if ($mode === 'enforce' && $status !== 'valid') {
            return response()->json([
                'message' => 'This app version could not be verified.',
                'code' => 'APP_CHECK_REQUIRED',
            ], 401);
        }

        return $next($request);
    }
}
