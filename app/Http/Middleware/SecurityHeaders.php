<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response as IlluminateResponse;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Adds the HTTP security headers to every response handled by Laravel.
 * Policy and reasoning: docs/security.md#http-security-headers.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $headers = $response->headers;

        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'DENY');
        if (! $headers->has('Referrer-Policy')) {
            $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        }
        $headers->set('Permissions-Policy', (string) config('security.permissions_policy'));
        $headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $headers->set('Cross-Origin-Resource-Policy', 'same-origin');
        $headers->set('X-Permitted-Cross-Domain-Policies', 'none');
        $headers->remove('X-Powered-By');

        if (! $headers->has('Content-Security-Policy') && ! $this->isDebugErrorPage($response)) {
            $headers->set('Content-Security-Policy', $this->contentSecurityPolicy());
        }

        if ($this->shouldSendHsts($request)) {
            $hsts = 'max-age='.(int) config('security.hsts.max_age');
            if (config('security.hsts.include_subdomains')) {
                $hsts .= '; includeSubDomains';
            }
            $headers->set('Strict-Transport-Security', $hsts);
        }

        return $response;
    }

    public function contentSecurityPolicy(): string
    {
        /** @var array<string, list<string>> $directives */
        $directives = config('security.csp');

        // Development only: allow the Vite dev server (HMR) when it is running.
        // Never active in production because the "hot" file does not exist there.
        if (app()->isLocal() && Vite::isRunningHot()) {
            $origin = rtrim(trim((string) file_get_contents(Vite::hotFile())), '/');
            $websocket = preg_replace('/^http/', 'ws', $origin);
            $directives['script-src'][] = $origin;
            $directives['style-src'][] = $origin;
            $directives['style-src'][] = "'unsafe-inline'"; // Vite injects CSS via <style> during HMR
            $directives['connect-src'][] = $origin;
            $directives['connect-src'][] = (string) $websocket;
            $directives['img-src'][] = $origin;
            $directives['font-src'][] = $origin;
        }

        $policy = [];
        foreach ($directives as $directive => $sources) {
            $policy[] = $directive.' '.implode(' ', array_unique($sources));
        }

        if (app()->isProduction()) {
            $policy[] = 'upgrade-insecure-requests';
        }

        return implode('; ', $policy);
    }

    /**
     * Laravel's interactive debug page (APP_DEBUG=true, local only) needs inline
     * assets. Production can never reach this branch because APP_DEBUG is
     * forced off there (AppServiceProvider).
     */
    private function isDebugErrorPage(Response $response): bool
    {
        return config('app.debug') === true
            && $response instanceof IlluminateResponse
            && $response->exception !== null
            && ! $response->exception instanceof HttpExceptionInterface;
    }

    private function shouldSendHsts(Request $request): bool
    {
        return config('security.hsts.enabled') === true
            && app()->isProduction()
            && $request->isSecure();
    }
}
