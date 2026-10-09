<?php

namespace App\Http\Controllers\Public;

use App\Contracts\Routable;
use App\Models\Gallery;
use App\Models\Media;
use App\Services\Content\ContentUsage;
use App\Services\Content\MediaStorage;
use App\Services\Content\ReferenceProtection;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaController
{
    public function __invoke(Request $request, Media $media, MediaStorage $storage, ContentUsage $usage): StreamedResponse
    {
        abort_unless($media->isPubliclyReachable() && (! $media->isImage() || $media->hasAccessibleAlternative()), 404);
        $reachable = false;
        foreach ($usage->mediaOwners($media) as $owner) {
            if ($owner instanceof Gallery && $owner->publicPath() === null) {
                $contexts = app(ReferenceProtection::class)->liveOwners($owner);
                $hasContext = $owner->isPubliclyReachable() && collect($contexts)->contains(fn ($context) => $context instanceof Routable && $context->isPubliclyReachable() && $context->publicPath() !== null);
                if ($hasContext) {
                    $reachable = true;
                    break;
                }

                continue;
            }
            if (method_exists($owner, 'isPubliclyReachable') && $owner->isPubliclyReachable()) {
                $reachable = true;
                break;
            }
        }
        abort_unless($reachable, 404);

        $data = $request->validate(['width' => ['nullable', Rule::in([480, 960, 1440])]]);

        return $storage->response($media, isset($data['width']) ? (int) $data['width'] : null);
    }
}
