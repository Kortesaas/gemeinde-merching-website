<?php

namespace App\Http\Controllers\Admin;

use App\Models\Media;
use App\Services\Content\MediaStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaFileController
{
    public function __invoke(Request $request, int $record, MediaStorage $storage): StreamedResponse
    {
        $media = Media::withTrashed()->findOrFail($record);
        Gate::authorize('view', $media);

        $data = $request->validate(['width' => ['nullable', Rule::in([480, 960, 1440])]]);

        return $storage->response($media, isset($data['width']) ? (int) $data['width'] : null);
    }
}
