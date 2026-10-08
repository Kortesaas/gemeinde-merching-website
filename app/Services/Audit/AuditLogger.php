<?php

namespace App\Services\Audit;

use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Lottery;

/**
 * Records administrative and security events.
 *
 * Privacy rule: metadata must only contain safe, non-secret values (ids,
 * field names, flags). Never pass passwords, tokens, codes, IP addresses,
 * user agents or form submissions.
 */
class AuditLogger
{
    public function __construct(private readonly AuthFactory $auth) {}

    /**
     * @param  array<string, scalar|null|array<array-key, scalar|null>>  $metadata
     */
    public function record(string $action, ?Model $subject = null, array $metadata = [], ?User $actor = null): AuditEvent
    {
        $actor ??= $this->auth->guard()->user();

        $event = AuditEvent::create([
            'user_id' => $actor instanceof User ? $actor->getKey() : null,
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'metadata' => $metadata === [] ? null : $metadata,
        ]);

        // Retention without cron: occasionally delete expired events.
        [$chances, $outOf] = config('audit.prune_lottery');
        Lottery::odds((int) $chances, (int) $outOf)
            ->winner(fn () => $this->pruneExpired((int) config('audit.prune_batch_size')))
            ->choose();

        return $event;
    }

    /**
     * Delete events older than the retention period.
     *
     * @param  int|null  $limit  maximum rows to delete (null = all)
     * @return int number of deleted events
     */
    public function pruneExpired(?int $limit = null): int
    {
        $query = (new AuditEvent)->prunable()->orderBy('id');

        if ($limit !== null) {
            return $query->limit($limit)->delete();
        }

        $deleted = 0;
        do {
            $batch = (clone $query)->limit(1000)->delete();
            $deleted += $batch;
        } while ($batch > 0);

        return $deleted;
    }
}
