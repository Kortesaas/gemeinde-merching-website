<?php

namespace App\Models;

use App\Enums\ProposalStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Proposed change to published content, reviewed and applied by a publisher.
 * Backend only; never affects the public site before it is applied.
 *
 * @property int $id
 * @property string $proposable_type
 * @property int $proposable_id
 * @property ProposalStatus $status
 * @property int|null $author_id
 * @property int|null $reviewer_id
 * @property string|null $summary
 * @property string|null $review_comment
 * @property int|null $base_revision_number
 * @property array<string, mixed> $base_snapshot
 * @property array<string, mixed> $payload
 * @property CarbonImmutable|null $submitted_at
 * @property CarbonImmutable|null $reviewed_at
 * @property int|null $applied_revision_number
 */
class ContentProposal extends Model
{
    /** Columns are written by ProposalService only. */
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => ProposalStatus::class,
            'base_snapshot' => 'array',
            'payload' => 'array',
            'submitted_at' => 'immutable_datetime',
            'reviewed_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function proposable(): MorphTo
    {
        return $this->morphTo()->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->whereIn('status', [ProposalStatus::Draft->value, ProposalStatus::Submitted->value]);
    }

    public function displayTitle(): string
    {
        return 'Änderungsvorschlag #'.$this->id;
    }
}
