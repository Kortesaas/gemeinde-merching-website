<?php

namespace App\Services\Audit;

use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Database\Eloquent\Model;

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

        return AuditEvent::create([
            'user_id' => $actor instanceof User ? $actor->getKey() : null,
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'metadata' => $metadata === [] ? null : $metadata,
        ]);
    }
}
