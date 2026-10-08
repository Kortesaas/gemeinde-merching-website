<?php

namespace App\Models\Concerns;

use App\Enums\PublicationState;
use App\Enums\PublicationStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Editorial publication lifecycle (columns: status, publish_at, expires_at,
 * archived_at – all timestamps UTC).
 *
 * Public visibility is computed from the current UTC time on every query, so
 * scheduled publication and expiry need no cron job:
 *
 *   status = published AND publish_at <= now AND (expires_at IS NULL OR expires_at > now)
 *
 * @property PublicationStatus $status
 * @property CarbonImmutable|null $publish_at
 * @property CarbonImmutable|null $expires_at
 * @property CarbonImmutable|null $archived_at
 */
trait HasPublication
{
    public function initializeHasPublication(): void
    {
        $this->mergeCasts([
            'status' => PublicationStatus::class,
            'publish_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'archived_at' => 'immutable_datetime',
        ]);

        if (! array_key_exists('status', $this->attributes)) {
            $this->attributes['status'] = PublicationStatus::Draft->value;
        }
    }

    /**
     * Currently public (used for all current listings and detail pages).
     *
     * @param  Builder<static>  $query
     */
    public function scopeVisible(Builder $query): void
    {
        $now = now();

        $query->where($this->qualifyColumn('status'), PublicationStatus::Published->value)
            ->whereNotNull($this->qualifyColumn('publish_at'))
            ->where($this->qualifyColumn('publish_at'), '<=', $now)
            ->where(fn (Builder $q) => $q->whereNull($this->qualifyColumn('expires_at'))
                ->orWhere($this->qualifyColumn('expires_at'), '>', $now));
    }

    /**
     * Was public and has ended (expired or archived) – for public archives
     * such as past notices. Never includes drafts or never-published content.
     *
     * @param  Builder<static>  $query
     */
    public function scopePublicArchive(Builder $query): void
    {
        $now = now();

        $query->whereNotNull($this->qualifyColumn('publish_at'))
            ->where($this->qualifyColumn('publish_at'), '<=', $now)
            ->where(fn (Builder $q) => $q
                ->where(fn (Builder $q) => $q->where($this->qualifyColumn('status'), PublicationStatus::Published->value)
                    ->whereNotNull($this->qualifyColumn('expires_at'))
                    ->where($this->qualifyColumn('expires_at'), '<=', $now))
                ->orWhere($this->qualifyColumn('status'), PublicationStatus::Archived->value));
    }

    public function publicationState(): PublicationState
    {
        $now = now();

        return match (true) {
            $this->status === PublicationStatus::Draft => PublicationState::Draft,
            $this->status === PublicationStatus::Archived => PublicationState::Archived,
            $this->publish_at === null || $this->publish_at->greaterThan($now) => PublicationState::Scheduled,
            $this->expires_at !== null && $this->expires_at->lessThanOrEqualTo($now) => PublicationState::Expired,
            default => PublicationState::Published,
        };
    }

    public function isVisible(): bool
    {
        return $this->publicationState() === PublicationState::Published;
    }

    public function isInPublicArchive(): bool
    {
        $state = $this->publicationState();

        return ($state === PublicationState::Expired || $state === PublicationState::Archived)
            && $this->publish_at !== null && $this->publish_at->lessThanOrEqualTo(now());
    }

    /**
     * Whether expired/archived records stay reachable at their URL (e.g. the
     * notice archive). Override per model.
     */
    public function keepsPublicArchive(): bool
    {
        return false;
    }

    public function isPubliclyReachable(): bool
    {
        if (($this->getAttributes()['deleted_at'] ?? null) !== null) {
            return false;
        }

        return $this->isVisible() || ($this->keepsPublicArchive() && $this->isInPublicArchive());
    }

    /**
     * Content that is (or was, or will be) public can only be changed by
     * users with publish permission until a review workflow exists.
     */
    public function isPublicationLocked(): bool
    {
        return $this->status !== PublicationStatus::Draft;
    }
}
