<?php

namespace App\Http\Controllers\Admin;

use App\Models\Media;
use App\Services\Content\MediaStorage;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaFileController
{
    public function __invoke(int $record, MediaStorage $storage): StreamedResponse
    {
        $media = Media::withTrashed()->findOrFail($record);
        Gate::authorize('view', $media);

        return $storage->response($media);
    }
}
