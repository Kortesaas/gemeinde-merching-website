<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Services\Content\DocumentStorage;
use App\Services\Seo\SeoMetadata;
use App\Support\Routing\PublicPath;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stable public download URL for documents without their own route:
 * /download/{id}/{filename}. Stateless (no cookies).
 *
 * - Only publicly reachable documents (published, or in the public archive).
 * - A deliberately assigned route (e.g. a migrated legacy file URL) is the
 *   canonical address and takes precedence: one 301 there.
 * - A wrong or outdated filename segment gets one 301 to the current one.
 */
class DocumentDownloadController extends Controller
{
    public function __invoke(Request $request, int $document, string $filename, DocumentStorage $storage): Response
    {
        $model = Document::query()->find($document);

        abort_if($model === null || ! $model->isPubliclyReachable(), 404);

        $canonical = $model->downloadPath();
        if (rawurldecode($request->getPathInfo()) !== $canonical) {
            $query = $request->getQueryString();

            return new RedirectResponse(rtrim(url('/'), '/').PublicPath::toUrl($canonical).($query ? '?'.$query : ''), 301);
        }

        abort_unless($storage->exists($model), 404);

        $response = $storage->response($model);
        $seo = app(SeoMetadata::class)->forModel($model);
        $response->headers->set('Link', '<'.$seo->canonical.'>; rel="canonical"');
        $response->headers->set('X-Robots-Tag', $seo->robots);

        return $response;
    }
}
