<?php

namespace App\Http\Middleware;

use App\Contracts\Routable;
use App\Models\Document;
use App\Models\LegacyUrl;
use App\Models\Media;
use App\Support\Routing\PublicPath;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveLegacyUrl
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethodSafe()) {
            return $next($request);
        }
        $path = rtrim(rawurldecode($request->getPathInfo()), '/') ?: '/';
        $query = $request->query();
        $identifiers = array_intersect(array_keys($query), ['wpdmdl', 'p', 'page_id', 'wpfb_dl', 'download_id', 'attachment_id']);
        if ($identifiers !== []) {
            abort_unless(count($identifiers) === 1 && array_diff(array_keys($query), [...$identifiers, 'refresh']) === [], 404);
            $name = array_values($identifiers)[0];
            $value = $query[$name];
            abort_unless(is_string($value) && preg_match('/^[0-9]{1,10}$/D', $value), 404);
            $path .= '?'.$name.'='.(int) $value;
        }
        $legacy = LegacyUrl::query()->where('url_hash', hash('sha256', $path))->first();
        if ($legacy === null) {
            abort_if($identifiers !== [], 404);

            return $next($request);
        }
        $target = $legacy->target;
        abort_unless($target !== null && method_exists($target, 'isPubliclyReachable') && $target->isPubliclyReachable(), 404);
        $destination = $legacy->getAttribute('destination');
        if ($destination === null) {
            $destination = match (true) {
                $target instanceof Document => $target->downloadPath(),
                $target instanceof Media => '/medien/'.$target->getKey(),
                $target instanceof Routable => $target->publicPath(),
                default => null,
            };
        }
        abort_unless(is_string($destination) && str_starts_with($destination, '/') && ! str_starts_with($destination, '//'), 404);
        if ($identifiers === [] && rawurldecode($request->getPathInfo()) === $destination) {
            return $next($request);
        }

        return redirect(PublicPath::toUrl($destination), 301);
    }
}
