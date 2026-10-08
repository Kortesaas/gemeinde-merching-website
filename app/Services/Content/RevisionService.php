<?php

namespace App\Services\Content;

use App\Contracts\Revisionable;
use App\Models\ContentRevision;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Content revisions: list, inspect, record and restore editorial states.
 *
 * - Snapshots contain only the model's revisionAttributes() and
 *   revisionRelations() (allowlist) – no IDs of system tables, timestamps,
 *   secrets or file storage data.
 * - Restoring applies the editorial content of an old revision and records a
 *   NEW revision; history is never deleted or rewritten.
 * - Publication state (status, publish window) is not restored: going live
 *   is always a separate, authorised decision.
 * - Revisions are independent of the audit log and its 730-day retention
 *   (config/revisions.php).
 */
class RevisionService
{
    private const SCHEMA = 1;

    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @return Collection<int, ContentRevision>
     */
    public function list(Model&Revisionable $model): Collection
    {
        return $model->revisions()->with('user')->get();
    }

    /**
     * Record the current state. Returns null if nothing changed since the last revision.
     */
    public function record(Model&Revisionable $model, ?User $editor, ?string $summary = null): ?ContentRevision
    {
        $snapshot = $this->snapshot($model);
        /** @var ContentRevision|null $latest */
        $latest = $model->revisions()->first();

        // MySQL's JSON type reorders object keys: compare in canonical order.
        if ($latest !== null && self::canonical($latest->snapshot) === self::canonical($snapshot)) {
            return null;
        }

        return ContentRevision::create([
            'revisionable_type' => $model->getMorphClass(),
            'revisionable_id' => $model->getKey(),
            'revision_number' => ($latest->revision_number ?? 0) + 1,
            'user_id' => $editor?->getKey(),
            'summary' => $summary === null ? null : mb_substr($summary, 0, 255),
            'snapshot' => $snapshot,
        ]);
    }

    /**
     * @return array{schema: int, attributes: array<string, mixed>, relations: array<string, list<array<string, mixed>>>}
     */
    public function snapshot(Model&Revisionable $model): array
    {
        // Always read the persisted state so raw values compare consistently.
        $fresh = $model->newQueryWithoutScopes()->whereKey($model->getKey())->firstOrFail();

        $attributes = [];
        foreach ($model->revisionAttributes() as $name) {
            $attributes[$name] = $this->normalizeValue($fresh->getAttributes()[$name] ?? null);
        }

        $relations = [];
        foreach ($model->revisionRelations() as $relation => $pivotColumns) {
            $relations[$relation] = $this->relationRows($model, $relation, $pivotColumns);
        }

        return ['schema' => self::SCHEMA, 'attributes' => $attributes, 'relations' => $relations];
    }

    /**
     * Restore the editorial state of $revision as a new revision.
     */
    public function restore(ContentRevision $revision, User $editor): ContentRevision
    {
        $model = $revision->revisionable;
        if (! $model instanceof Revisionable) {
            throw new LogicException('Revision target does not support revisions.');
        }

        return DB::transaction(function () use ($model, $revision, $editor) {
            $publicationFields = ['status', 'publish_at', 'expires_at', 'archived_at'];
            $attributes = Arr::except(
                Arr::only($revision->snapshot['attributes'], $model->revisionAttributes()),
                $publicationFields,
            );

            $model->forceFill($attributes)->save();

            foreach ($revision->snapshot['relations'] as $relation => $rows) {
                if (array_key_exists($relation, $model->revisionRelations())) {
                    $this->restoreRelation($model, $relation, $rows);
                }
            }

            $new = $this->record($model->refresh(), $editor, "Version {$revision->revision_number} wiederhergestellt")
                ?? $model->revisions()->firstOrFail();

            $this->audit->record('revision.restored', $model, [
                'type' => $model->getMorphClass(),
                'restored_revision' => $revision->revision_number,
                'new_revision' => $new->revision_number,
            ], actor: $editor);

            return $new;
        });
    }

    /**
     * @param  list<string>  $pivotColumns
     * @return list<array<string, mixed>>
     */
    private function relationRows(Model&Revisionable $model, string $relation, array $pivotColumns): array
    {
        /** @var BelongsToMany<Model, Model> $query */
        $query = $model->{$relation}();
        if (in_array(SoftDeletes::class, class_uses_recursive($query->getRelated()), true)) {
            $query->withTrashed(); // @phpstan-ignore method.notFound
        }

        $rows = $query->get()->map(function (Model $related) use ($pivotColumns) {
            $row = ['id' => $related->getKey()];
            foreach ($pivotColumns as $column) {
                $row[$column] = $this->normalizeValue($related->getRelation('pivot')->getAttribute($column));
            }

            return $row;
        })->all();

        usort($rows, fn (array $a, array $b) => [$a['slot'] ?? '', $a['sort_order'] ?? 0, $a['id']] <=> [$b['slot'] ?? '', $b['sort_order'] ?? 0, $b['id']]);

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function restoreRelation(Model&Revisionable $model, string $relation, array $rows): void
    {
        /** @var BelongsToMany<Model, Model> $query */
        $query = $model->{$relation}();
        $related = $query->getRelated();
        $existing = $related->newQueryWithoutScopes()->whereKey(array_column($rows, 'id'))->pluck($related->getKeyName())->all();

        $query->detach();
        foreach ($rows as $row) {
            if (in_array($row['id'], $existing, false)) {
                $query->attach($row['id'], Arr::except($row, ['id']));
            }
        }
    }

    /**
     * @param  array<mixed>  $data
     * @return array<mixed>
     */
    private static function canonical(array $data): array
    {
        if (! array_is_list($data)) {
            ksort($data);
        }

        return array_map(fn ($v) => is_array($v) ? self::canonical($v) : $v, $data);
    }

    private function normalizeValue(mixed $value): mixed
    {
        return match (true) {
            $value instanceof \BackedEnum => $value->value,
            $value instanceof \DateTimeInterface => $value->format('Y-m-d H:i:s'),
            is_bool($value) => (int) $value,
            default => $value,
        };
    }
}
