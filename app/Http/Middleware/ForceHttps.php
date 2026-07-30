<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForceHttps
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->environment('production') && ! $request->secure()) {
            $configuredHost = parse_url((string) config('app.url'), PHP_URL_HOST);
            $host = is_string($configuredHost) && $configuredHost !== ''
                ? $configuredHost
                : $request->getHost();

            return redirect()->to('https://'.$host.$request->getRequestUri(), 308);
        }

        return $next($request);
    }
}
