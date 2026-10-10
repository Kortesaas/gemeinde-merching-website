<?php

use App\Contracts\Routable;
use App\Models\BudgetPlan;
use App\Models\Document;
use App\Models\Gallery;
use App\Models\Media;
use App\Models\SourceReference;
use App\Models\User;
use App\Services\Content\SourceOnlyBudget;
use App\Services\Migration\PublicContentImporter;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

// Read-only audit of this task's isolated local database and prepared public files.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('local') || DB::connection()->getDatabaseName() !== 'merching_migration') {
    throw new RuntimeException('Audit requires the isolated local migration database.');
}
$manifest = json_decode(file_get_contents(base_path('migration-source/prepared/manifest.json')), true, flags: JSON_THROW_ON_ERROR);
$failures = [];
$identities = [];
$galleries = [];
$budgets = [];
$dates = 0;
$files = 0;
foreach ([...$manifest['records'], ...$manifest['assets']] as $record) {
    $references = SourceReference::query()->where('source_system', PublicContentImporter::SOURCE)->where('source_id', $record['key'])->get();
    $target = $references->first()?->referenceable;
    if ($references->count() !== 1 || $target === null) {
        $failures[] = $record['key'].': missing/duplicate source identity';

        continue;
    }
    $identities[$record['key']] = [$target->getMorphClass(), $target->getKey()];
    if (method_exists($target, 'isPubliclyReachable') && ! $target->isPubliclyReachable()) {
        $failures[] = $record['key'].': public destination not reachable';
    }
    if ($target instanceof Routable && isset($record['path']) && $target->publicPath() !== $record['path']) {
        $failures[] = $record['key'].': canonical path changed';
    }
    if (isset($target->getAttributes()['publish_at'])) {
        $dates++;
        if ($target->getAttribute('publish_at')->utc()->format('Y-m-d H:i:s') !== $record['date']) {
            $failures[] = $record['key'].': source publication timestamp changed';
        }
    }
    if ($target instanceof Media || $target instanceof Document) {
        $files++;
        $disk = Storage::disk(config('uploads.disk'));
        if (! $disk->exists($target->file_path) || hash('sha256', $disk->get($target->file_path)) !== $target->getAttribute('sha256')) {
            $failures[] = $record['key'].': stored file checksum mismatch';
        }
        if ($target instanceof Document && $target->getAttribute('sha256') !== $record['sha256']) {
            $failures[] = $record['key'].': document original bytes changed';
        }
        if (hash_file('sha256', base_path($record['file'])) !== $record['sha256']) {
            $failures[] = $record['key'].': immutable prepared/recovered original changed';
        }
    }
    if ($target instanceof Gallery) {
        $actual = $target->items()->orderBy('sort_order')->pluck('media_id')->all();
        $expected = array_map(fn ($key) => SourceReference::query()->where('source_system', PublicContentImporter::SOURCE)->where('source_id', $key)->firstOrFail()->getAttribute('referenceable_id'), $record['items']);
        if ($actual !== $expected) {
            $failures[] = $record['key'].': gallery image membership/order changed';
        }
        $galleries[] = ['title' => $target->displayTitle(), 'items' => count($actual), 'path' => $target->publicPath()];
    }
    if ($target instanceof BudgetPlan) {
        $receipt = $target->publications()->sole();
        foreach ($receipt->getAttribute('source_manifest') as $entry) {
            app(SourceOnlyBudget::class)->source($target, $receipt, $entry['id']);
        }
        $budgets[] = ['title' => $target->displayTitle(), 'sources' => count($receipt->getAttribute('source_manifest')), 'source_only' => $receipt->getAttribute('source_only'), 'accessibility' => $receipt->getAttribute('accessibility_status')];
    }
}
ksort($identities);
$foreignReferences = SourceReference::query()->where('source_system', '!=', PublicContentImporter::SOURCE)->count();
if ($foreignReferences !== 0 || User::query()->count() !== 0) {
    $failures[] = 'Unexpected nonmigration source identities or imported users';
}
$report = [
    'database' => DB::connection()->getDatabaseName(),
    'failures' => $failures,
    'targets' => count($identities),
    'identity_fingerprint' => hash('sha256', json_encode($identities, JSON_THROW_ON_ERROR)),
    'publication_timestamps_checked' => $dates,
    'stored_and_original_files_checked' => $files,
    'nonmigration_source_references' => $foreignReferences,
    'users' => User::query()->count(),
    'search_entries' => DB::table('search_entries')->count(),
    'legacy_urls' => DB::table('legacy_urls')->count(),
    'source_references' => SourceReference::query()->count(),
    'galleries' => $galleries,
    'budgets' => $budgets,
];
$json = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n";
file_put_contents(base_path('docs/migration/local/integrity.json'), $json);
echo $json;
exit($failures === [] ? 0 : 1);
