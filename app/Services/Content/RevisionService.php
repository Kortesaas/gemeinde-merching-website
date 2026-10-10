<?php

namespace App\Services\Content;

use App\Contracts\Revisionable;
use App\Models\BudgetPlan;
use App\Models\ContentRevision;
use App\Models\CouncilMember;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Quality\QualityChecks;
use App\Services\Search\SearchIndexer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
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
    /** 3 = ordered content compositions; schema 1/2 snapshots remain readable. */
    private const SCHEMA = 3;

    public const PUBLICATION_FIELDS = ['status', 'publish_at', 'expires_at', 'archived_at'];

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
     * @return array{schema: int, attributes: array<string, mixed>, relations: array<string, list<array<string, mixed>>>, collections: array<string, list<array<string, mixed>>>}
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

        $collections = [];
        foreach ($model->revisionCollections() as $relation => $columns) {
            $collections[$relation] = $this->collectionRows($model, $relation, $columns);
        }

        return ['schema' => self::SCHEMA, 'attributes' => $attributes, 'relations' => $relations, 'collections' => $collections];
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

        return app(BudgetWorkflow::class)->transaction(function () use ($model, $revision, $editor) {
            app(BudgetWorkflow::class)->lock($model);
            if ($model instanceof BudgetPlan) {
                Gate::forUser($editor)->authorize('restoreRevision', $model);
            }
            $this->applySnapshot($model, $revision->snapshot);

            app(BudgetWorkflow::class)->finish($model, $editor);
            app(QualityChecks::class)->enforcePublicAssets($model);

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
     * Write editorial content from a snapshot to the model (no revision is
     * recorded). Publication fields are never applied. Optionally limited to
     * the given attribute/relation/collection names (used to apply only the
     * parts a change proposal actually changed).
     *
     * @param  array<string, mixed>  $snapshot
     * @param  list<string>|null  $onlyAttributes
     * @param  list<string>|null  $onlyRelations
     * @param  list<string>|null  $onlyCollections
     */
    public function applySnapshot(Model&Revisionable $model, array $snapshot, ?array $onlyAttributes = null, ?array $onlyRelations = null, ?array $onlyCollections = null): void
    {
        $allowed = array_values(array_diff($model->revisionAttributes(), self::PUBLICATION_FIELDS));
        $attributes = Arr::only((array) ($snapshot['attributes'] ?? []), $onlyAttributes === null ? $allowed : array_intersect($allowed, $onlyAttributes));

        // Revisions created before portrait support represented a member without a portrait.
        if ($model instanceof CouncilMember && $onlyAttributes === null && ! array_key_exists('portrait_id', $snapshot['attributes'] ?? [])) {
            $attributes['portrait_id'] = null;
        }
        if ($model instanceof BudgetPlan && $onlyAttributes === null && ! array_key_exists('topic', $snapshot['attributes'] ?? [])) {
            $attributes['topic'] = 'Haushaltsplan';
        }
        $model->forceFill($attributes)->save();

        foreach ((array) ($snapshot['relations'] ?? []) as $relation => $rows) {
            if (array_key_exists($relation, $model->revisionRelations()) && ($onlyRelations === null || in_array($relation, $onlyRelations, true))) {
                $this->restoreRelation($model, $relation, $rows);
            }
        }

        foreach ($model->revisionCollections() as $relation => $columns) {
            $rows = array_values((array) ($snapshot['collections'][$relation] ?? []));
            if ($onlyCollections === null || in_array($relation, $onlyCollections, true)) {
                $this->restoreCollection($model, $relation, $columns, $rows);
            }
        }
        app(SearchIndexer::class)->sync($model);
    }

    /**
     * Rows of a HasMany child collection (e.g. service aliases).
     *
     * @param  list<string>  $columns
     * @return list<array<string, mixed>>
     */
    private function collectionRows(Model&Revisionable $model, string $relation, array $columns): array
    {
        /** @var HasMany<Model, Model>|MorphMany<Model, Model> $query */
        $query = $model->{$relation}();

        $rows = $query->get()->map(function (Model $child) use ($columns) {
            $row = [];
            foreach ($columns as $column) {
                $row[$column] = $this->normalizeValue($child->getAttributes()[$column] ?? null);
            }

            return $row;
        })->all();

        if (in_array('sort_order', $columns, true)) {
            // Stable sort preserves the explicit query's ID tie order.
            usort($rows, fn (array $a, array $b) => $a['sort_order'] <=> $b['sort_order']);
        } else {
            usort($rows, fn (array $a, array $b) => array_values($a) <=> array_values($b));
        }

        return $rows;
    }

    /**
     * @param  list<string>  $columns
     * @param  list<array<string, mixed>>  $rows
     */
    private function restoreCollection(Model&Revisionable $model, string $relation, array $columns, array $rows): void
    {
        /** @var HasMany<Model, Model>|MorphMany<Model, Model> $query */
        $query = $model->{$relation}();
        $query->delete();

        foreach ($rows as $row) {
            $query->forceCreate(Arr::only($row, $columns) + [$query->getForeignKeyName() => $model->getKey()]);
        }
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
