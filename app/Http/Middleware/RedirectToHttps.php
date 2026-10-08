<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Redirects plain-HTTP requests to HTTPS (production default). The target host
 * comes from APP_URL, never from the request, to avoid open redirects.
 */
class RedirectToHttps
{
    public function handle(Request $request, Closure $next): Response
    {
        if (config('security.force_https') !== true || $request->isSecure()) {
            return $next($request);
        }

        $host = parse_url((string) config('app.url'), PHP_URL_HOST) ?: $request->getHost();

        return new RedirectResponse('https://'.$host.$request->getRequestUri(), 301);
    }
}
