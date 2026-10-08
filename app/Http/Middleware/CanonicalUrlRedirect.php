<?php

namespace App\Http\Middleware;

use App\Services\Routing\RouteManager;
use App\Support\Routing\PublicPath;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Permanently redirects to the canonical origin (scheme + host of APP_URL,
 * production: https://www.gemeinde-merching.de) in a single hop:
 *
 * - plain HTTP when HTTPS is enforced (security.force_https), and
 * - alias hosts listed in REDIRECT_HOSTS (e.g. the non-www domain).
 *
 * Path and query string are preserved, so existing URLs keep working. To
 * avoid redirect chains, GET/HEAD requests go straight to the FINAL target
 * (slashless canonical path, current path of moved content, or the
 * destination of a legacy redirect). The target host always comes from
 * APP_URL, never from the request (no open redirect).
 */
class CanonicalUrlRedirect
{
    public function handle(Request $request, Closure $next): Response
    {
        $isAliasHost = in_array(strtolower($request->getHost()), $this->redirectHosts(), true);
        $needsHttps = config('security.force_https') === true && ! $request->isSecure();

        if (! $isAliasHost && ! $needsHttps) {
            return $next($request);
        }

        // 308 keeps the method and body of non-GET requests (e.g. form posts).
        if (! $request->isMethodSafe()) {
            return new RedirectResponse($this->canonicalOrigin($request).$request->getRequestUri(), 308);
        }

        try {
            $final = app(RouteManager::class)->finalLocation($request->getPathInfo());
        } catch (Throwable) {
            $final = ['location' => PublicPath::withoutTrailingSlash(rawurldecode($request->getPathInfo())), 'status' => 301];
        }

        if (! str_starts_with($final['location'], '/')) {
            return new RedirectResponse($final['location'], $final['status']); // external legacy target
        }

        $query = $request->getQueryString();

        return new RedirectResponse(
            $this->canonicalOrigin($request).PublicPath::toUrl($final['location']).($query ? '?'.$query : ''),
            $final['status'],
        );
    }

    private function canonicalOrigin(Request $request): string
    {
        $url = parse_url((string) config('app.url'));
        $host = $url['host'] ?? $request->getHost();
        $scheme = config('security.force_https') === true ? 'https' : ($url['scheme'] ?? 'https');
        $port = isset($url['port']) ? ':'.$url['port'] : '';

        return $scheme.'://'.$host.$port;
    }

    /**
     * @return list<string>
     */
    private function redirectHosts(): array
    {
        return array_values(array_map('strtolower', (array) config('security.redirect_hosts')));
    }
}
