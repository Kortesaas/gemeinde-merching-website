<?php

namespace App\Http\Controllers\Public;

use App\Contracts\Routable;
use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\PublicRoute;
use App\Models\Redirect;
use App\Services\Content\DocumentStorage;
use App\Services\Routing\RouteManager;
use App\Support\Routing\PublicPath;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves every public path that no explicit route handles (stateless
 * "public" group, no cookies):
 *
 * 1. content route → render the record (if publicly reachable right now);
 *    former paths and case/trailing-slash variants → ONE 301 to the
 *    slashless canonical path (query string kept);
 * 2. redirect (also for "/legacy/" with slash) → one hop to its destination
 *    (301/302) or 410 Gone;
 * 3. otherwise 404.
 *
 * Rendering is a minimal placeholder until the design system is implemented.
 */
class ContentController extends Controller
{
    public function __invoke(Request $request, RouteManager $routes, DocumentStorage $storage): Response
    {
        $target = $routes->resolve($request->getPathInfo());

        if ($target instanceof Redirect) {
            abort_if($target->status_code === 410 || $target->destination === null, 410);

            return $this->redirect($request, (string) $target->destination, $target->status_code);
        }

        if (! $target instanceof PublicRoute) {
            abort(404);
        }

        $model = $target->routable;
        abort_unless($model instanceof Routable && $model->isPubliclyReachable(), 404);

        if (! $target->is_canonical) {
            /** @var PublicRoute|null $canonical */
            $canonical = $model->canonicalRoute()->where('is_active', true)->first();
            abort_if($canonical === null, 404);

            return $this->redirect($request, $canonical->path, 301);
        }

        if (rawurldecode($request->getPathInfo()) !== $target->path) {
            return $this->redirect($request, $target->path, 301);
        }

        if ($model instanceof Document) {
            abort_unless($storage->exists($model), 404);

            return $storage->response($model);
        }

        return response()->view('public.content', ['model' => $model]);
    }

    private function redirect(Request $request, string $destination, int $status): RedirectResponse
    {
        if (str_starts_with($destination, '/')) {
            $query = $request->getQueryString();
            $destination = rtrim(url('/'), '/').PublicPath::toUrl($destination).($query ? '?'.$query : '');
        }

        return new RedirectResponse($destination, $status);
    }
}
