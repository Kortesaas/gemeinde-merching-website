<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Services\Content\DocumentStorage;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Backend download (also for drafts and documents in the recycle bin).
 */
class DocumentFileController extends Controller
{
    public function __invoke(int $record, DocumentStorage $storage): StreamedResponse
    {
        $document = Document::withTrashed()->findOrFail($record);
        Gate::authorize('view', $document);
        abort_unless($storage->exists($document), 404);

        return $storage->response($document);
    }
}
