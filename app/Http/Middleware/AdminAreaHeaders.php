<?php

namespace App\Http\Middleware;

use App\Support\AdminArea;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every backend response – including login, errors and 404s below the backend
 * prefix – must never be cached or indexed. Registered globally (not per route)
 * so it also covers responses for unknown backend URLs.
 */
class AdminAreaHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (AdminArea::matches($request)) {
            $response->headers->set('Cache-Control', 'no-store, private');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');
            $response->headers->set('Referrer-Policy', 'same-origin');
        }

        return $response;
    }
}
