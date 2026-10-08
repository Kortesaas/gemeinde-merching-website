<?php

namespace App\Http\Middleware;

use App\Support\AdminArea;
use App\Support\SearchEngineIndexing;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends "X-Robots-Tag: noindex, nofollow" for the public website unless
 * indexing is explicitly enabled in production (see SearchEngineIndexing).
 */
class SearchEngineIndexingHeader
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! AdminArea::matches($request) && ! SearchEngineIndexing::allowed()) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        return $response;
    }
}
