<?php

namespace App\Http\Controllers\Public;

use App\Models\Media;
use App\Services\Content\ContentUsage;
use App\Services\Content\MediaStorage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaController
{
    public function __invoke(Media $media, MediaStorage $storage, ContentUsage $usage): StreamedResponse
    {
        abort_unless($media->isPubliclyReachable() && (! $media->isImage() || $media->hasAccessibleAlternative()), 404);
        $reachable = false;
        foreach ($usage->mediaOwners($media) as $owner) {
            if (method_exists($owner, 'isPubliclyReachable') && $owner->isPubliclyReachable()) {
                $reachable = true;
                break;
            }
        }
        abort_unless($reachable, 404);

        return $storage->response($media);
    }
}
