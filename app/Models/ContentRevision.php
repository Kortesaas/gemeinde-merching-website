<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One editorial version of a record (see App\Services\Content\RevisionService).
 * Immutable once written.
 *
 * @property int $id
 * @property string $revisionable_type
 * @property int $revisionable_id
 * @property int $revision_number
 * @property int|null $user_id
 * @property string|null $summary
 * @property array{schema: int, attributes: array<string, mixed>, relations: array<string, list<array<string, mixed>>>} $snapshot
 * @property CarbonImmutable $created_at
 */
class ContentRevision extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Revisions are immutable.'));
    }

    protected function casts(): array
    {
        return ['snapshot' => 'array', 'revision_number' => 'integer', 'created_at' => 'immutable_datetime'];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function revisionable(): MorphTo
    {
        return $this->morphTo()->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
