<?php

namespace App\Console\Commands;

use App\Services\Audit\AuditLogger;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Deletes all audit events older than config('audit.retention_days').
 * Expired events are also removed opportunistically while the application
 * runs; this command is for deployments, WebCron or manual use.
 */
#[Signature('audit:prune')]
#[Description('Delete audit events older than the configured retention period')]
class PruneAuditEvents extends Command
{
    public function handle(AuditLogger $audit): int
    {
        $days = (int) config('audit.retention_days');

        if ($days <= 0) {
            $this->components->info('Automatic deletion is disabled (AUDIT_RETENTION_DAYS=0).');

            return self::SUCCESS;
        }

        $deleted = $audit->pruneExpired();

        $this->components->info("Deleted {$deleted} audit event(s) older than {$days} days.");

        return self::SUCCESS;
    }
}
