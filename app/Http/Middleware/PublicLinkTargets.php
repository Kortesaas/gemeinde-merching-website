<?php

namespace App\Http\Middleware;

use App\Models\PublicRoute;
use App\Support\Content\NewTabLinks;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Apply the public link policy after Blade/Markdown rendering, including tables. */
final class PublicLinkTargets
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $html = $response->getContent();
        if (str_starts_with((string) $response->headers->get('Content-Type'), 'text/html')
            && is_string($html) && str_contains($html, 'id="new-tab-description"')) {
            /** @var list<string> $documentPaths */
            $documentPaths = PublicRoute::query()->where('routable_type', 'document')->where('is_active', true)->pluck('path')->all();
            $response->setContent(NewTabLinks::apply($html, $request->getSchemeAndHttpHost(), $documentPaths));
        }

        return $response;
    }
}
