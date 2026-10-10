<?php

namespace App\Services\Content;

use App\Models\BudgetPlan;
use App\Models\BudgetPublication;
use App\Models\BudgetSource;
use Illuminate\Support\Facades\Storage;

/** Only immutable publication snapshots authorize unmerged original downloads. */
final class SourceOnlyBudget
{
    public function source(BudgetPlan $plan, BudgetPublication $receipt, int $id): BudgetSource
    {
        abort_unless($receipt->getAttribute('source_only') && $receipt->show_components, 404);
        $manifest = $receipt->getAttribute('source_manifest') ?? [];
        $entry = collect($manifest)->first(fn ($entry) => (int) $entry['id'] === $id);
        abort_unless(is_array($entry), 404);
        $source = $plan->sources()->findOrFail($id);
        abort_unless(hash_equals((string) $entry['sha256'], (string) $source->getAttribute('sha256')), 404);
        $disk = Storage::disk((string) config('uploads.disk'));
        abort_unless($disk->exists((string) $source->getAttribute('file_path')), 404);
        $bytes = (string) $disk->get((string) $source->getAttribute('file_path'));
        abort_unless(strlen($bytes) === (int) $entry['size_bytes'] && hash_equals((string) $entry['sha256'], hash('sha256', $bytes)), 404);

        return $source;
    }
}
