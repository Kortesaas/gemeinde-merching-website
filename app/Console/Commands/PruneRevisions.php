<?php

namespace App\Console\Commands;

use App\Models\ContentRevision;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Applies the (still undecided) revision retention policy from
 * config/revisions.php. Disabled by default: all revisions are kept.
 * Always keeps the newest `keep_latest` revisions of every record.
 */
#[Signature('revisions:prune')]
#[Description('Delete content revisions older than REVISION_RETENTION_DAYS (keeps the latest per record)')]
class PruneRevisions extends Command
{
    public function handle(): int
    {
        $days = config('revisions.retention_days');

        if ($days === null) {
            $this->components->info('Revision retention is not configured – all revisions are kept.');

            return self::SUCCESS;
        }

        $keep = max(1, (int) config('revisions.keep_latest'));
        $cutoff = now()->subDays((int) $days);
        $deleted = 0;

        $records = ContentRevision::query()->select('revisionable_type', 'revisionable_id')
            ->where('created_at', '<', $cutoff)->distinct()->get();

        foreach ($records as $record) {
            $protected = ContentRevision::query()
                ->where('revisionable_type', $record->revisionable_type)->where('revisionable_id', $record->revisionable_id)
                ->orderByDesc('revision_number')->limit($keep)->pluck('id');

            $deleted += DB::table('content_revisions')
                ->where('revisionable_type', $record->revisionable_type)->where('revisionable_id', $record->revisionable_id)
                ->where('created_at', '<', $cutoff)->whereNotIn('id', $protected)->delete();
        }

        $this->components->info("Deleted {$deleted} revision(s) older than {$days} days.");

        return self::SUCCESS;
    }
}
