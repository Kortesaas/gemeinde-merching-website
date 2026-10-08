<?php

namespace App\Services\Content;

use App\Enums\PublicationStatus;
use App\Exceptions\DomainRuleViolation;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * Applies status changes and publication windows (UTC) to publishable
 * models. Authorization (publish/archive permission) is checked by the
 * caller; this class enforces the allowed transitions and timestamp rules.
 */
class PublicationService
{
    /**
     * @return string|null audit action of the transition (e.g. "content.published") or null
     *
     * @throws DomainRuleViolation
     */
    public function apply(Model $model, PublicationStatus $target, ?CarbonImmutable $publishAt, ?CarbonImmutable $expiresAt): ?string
    {
        /** @var PublicationStatus $current */
        $current = $model->exists ? $model->getAttribute('status') : PublicationStatus::Draft;

        if (! $current->canTransitionTo($target)) {
            throw new DomainRuleViolation("Der Status kann nicht von „{$current->label()}“ zu „{$target->label()}“ geändert werden.", 'status');
        }

        if ($publishAt !== null && $expiresAt !== null && $expiresAt->lessThanOrEqualTo($publishAt)) {
            throw new DomainRuleViolation('Das Ende der Veröffentlichung muss nach dem Beginn liegen.', 'expires_at');
        }

        if ($target !== PublicationStatus::Draft && $publishAt === null) {
            $publishAt = now(); // "publish now"
        }

        $model->setAttribute('publish_at', $publishAt);
        $model->setAttribute('expires_at', $expiresAt);
        $model->setAttribute('status', $target);

        if ($target === PublicationStatus::Archived && $current !== PublicationStatus::Archived) {
            $model->setAttribute('archived_at', now());
        } elseif ($target !== PublicationStatus::Archived) {
            $model->setAttribute('archived_at', null);
        }

        return match (true) {
            $current === $target => null,
            $target === PublicationStatus::Published && $current === PublicationStatus::Draft => 'content.published',
            $target === PublicationStatus::Published => 'content.unarchived',
            $target === PublicationStatus::Archived => 'content.archived',
            default => 'content.unpublished',
        };
    }
}
