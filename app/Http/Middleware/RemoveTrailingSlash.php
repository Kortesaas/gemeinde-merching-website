<?php

namespace App\Http\Middleware;

use App\Support\Routing\PublicPath;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Canonical URLs have no trailing slash. Laravel matches explicit routes with
 * and without slash; this sends GET/HEAD requests for "/foo/" with one 301 to
 * "/foo" (query kept). The content fallback route is skipped because it
 * resolves slash variants itself directly to the final target (no chains).
 */
class RemoveTrailingSlash
{
    public function handle(Request $request, Closure $next): Response
    {
        $path = $request->getPathInfo();

        if ($path === '/' || ! str_ends_with($path, '/') || ! $request->isMethodSafe() || $request->route()?->isFallback) {
            return $next($request);
        }

        $query = $request->getQueryString();

        return new RedirectResponse(
            rtrim(url('/'), '/').PublicPath::toUrl(PublicPath::withoutTrailingSlash(rawurldecode($path))).($query ? '?'.$query : ''),
            301,
        );
    }
}
