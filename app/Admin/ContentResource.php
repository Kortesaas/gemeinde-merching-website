<?php

namespace App\Admin;

use App\Admin\Fields\Field;
use App\Admin\Fields\Lines;
use App\Contracts\Revisionable;
use App\Contracts\Routable;
use App\Enums\PublicationStatus;
use App\Exceptions\DomainRuleViolation;
use App\Models\User;
use App\Rules\SiteDateTime;
use App\Services\Audit\AuditLogger;
use App\Services\Content\PublicationService;
use App\Services\Content\RevisionService;
use App\Services\Routing\RouteManager;
use App\Support\Authorization\ContentType;
use App\Support\SiteTime;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;
use Throwable;

/**
 * Describes one entity for the functional admin CRUD: fields, validation,
 * list columns and the save workflow. The visual CMS design comes later; this
 * layer proves the domain model and keeps every rule server-side.
 *
 * @template TModel of Model
 */
abstract class ContentResource
{
    /** @var list<callable(): void> cleanup callbacks when saving fails (e.g. stored files) */
    protected array $onFailure = [];

    /**
     * @return class-string<TModel>
     */
    abstract public function model(): string;

    abstract public function type(): ContentType;

    /** URL segment below /verwaltung, e.g. "artikel". */
    abstract public function slug(): string;

    abstract public function label(): string;

    abstract public function pluralLabel(): string;

    /**
     * @param  TModel|null  $model
     * @return list<Field>
     */
    abstract public function fields(?Model $model): array;

    public function key(): string
    {
        return $this->type() === ContentType::Taxonomy ? $this->slug() : $this->type()->value;
    }

    /**
     * Columns for LIKE search in the list.
     *
     * @return list<string>
     */
    public function searchColumns(): array
    {
        return ['title'];
    }

    /**
     * Extra list columns: label => value.
     *
     * @param  TModel  $model
     * @return array<string, string>
     */
    public function columns(Model $model): array
    {
        return [];
    }

    /**
     * @return Builder<TModel>
     */
    public function query(): Builder
    {
        return ($this->model())::query();
    }

    public function isPublishable(): bool
    {
        return method_exists($this->model(), 'publicationState');
    }

    public function isRoutable(): bool
    {
        return is_subclass_of($this->model(), Routable::class);
    }

    public function usesRecycleBin(): bool
    {
        return in_array(SoftDeletes::class, class_uses_recursive($this->model()), true);
    }

    public function hasRevisions(): bool
    {
        return method_exists($this->model(), 'revisionAttributes');
    }

    public function hasPlacements(): bool
    {
        return method_exists($this->model(), 'documentSlots') || method_exists($this->model(), 'resourceSlots');
    }

    /**
     * @return TModel
     */
    public function find(int $id): Model
    {
        $query = $this->query();
        if ($this->usesRecycleBin()) {
            $query->withTrashed(); // @phpstan-ignore method.notFound
        }

        return $query->findOrFail($id);
    }

    /**
     * @return TModel
     */
    public function newModel(): Model
    {
        $class = $this->model();

        return new $class;
    }

    /**
     * Additional validation rules beyond the fields.
     *
     * @param  TModel|null  $model
     * @return array<string, list<mixed>>
     */
    public function rules(?Model $model): array
    {
        return [];
    }

    /**
     * Cross-field validation.
     *
     * @param  TModel|null  $model
     */
    public function after(Validator $validator, ?Model $model): void {}

    /**
     * Hook before saving (e.g. file upload, derived values).
     *
     * @param  TModel  $model
     * @param  array<string, mixed>  $data
     */
    protected function beforeSave(Model $model, array $data, Request $request): void {}

    /**
     * Hook before permanent deletion.
     *
     * @param  TModel  $model
     *
     * @throws DomainRuleViolation
     */
    public function beforeForceDelete(Model $model): void {}

    /**
     * @param  TModel  $model
     */
    public function afterForceDelete(Model $model): void {}

    /**
     * @param  TModel|null  $model
     * @return array<string, list<mixed>>
     */
    public function validationRules(?Model $model): array
    {
        $rules = [];
        foreach ($this->fields($model) as $field) {
            $rules += $field->validationRules($model);
        }

        if ($this->isPublishable()) {
            $rules['status'] = ['nullable', 'string', 'in:'.implode(',', array_map(fn ($s) => $s->value, PublicationStatus::cases()))];
            $rules['publish_at'] = ['nullable', 'string', new SiteDateTime];
            $rules['expires_at'] = ['nullable', 'string', new SiteDateTime];
        }

        if ($this->isRoutable()) {
            $rules['public_path'] = ['nullable', 'string', 'max:255'];
        }

        if ($this->hasRevisions()) {
            $rules['revision_summary'] = ['nullable', 'string', 'max:255'];
        }

        return $rules + $this->rules($model);
    }

    /**
     * @param  TModel|null  $model
     */
    public function validateAfter(Validator $validator, ?Model $model): void
    {
        $data = $validator->getData();

        foreach ($this->fields($model) as $field) {
            if ($field instanceof Lines && isset($data[$field->name])) {
                $lines = Lines::split($data[$field->name]);
                if (count($lines) > $field->maxLines) {
                    $validator->errors()->add($field->name, "Bitte höchstens {$field->maxLines} Einträge angeben.");
                }
                foreach ($lines as $line) {
                    $check = \Illuminate\Support\Facades\Validator::make(['line' => $line], ['line' => $field->lineRules], [], ['line' => "„{$line}“"]);
                    if ($check->fails()) {
                        $validator->errors()->add($field->name, $check->errors()->first('line'));
                        break;
                    }
                }
            }
        }

        if ($this->isPublishable() && ! $validator->errors()->hasAny(['publish_at', 'expires_at'])) {
            $start = SiteTime::fromInput($data['publish_at'] ?? null);
            $end = SiteTime::fromInput($data['expires_at'] ?? null);
            if ($start !== null && $end !== null && $end->lessThanOrEqualTo($start)) {
                $validator->errors()->add('expires_at', 'Das Ende der Veröffentlichung muss nach dem Beginn liegen.');
            }
        }

        $this->after($validator, $model);
    }

    /**
     * Delete: recycle bin for soft-deleting types, otherwise immediately.
     *
     * @param  TModel  $model
     */
    public function delete(Model $model, User $editor): void
    {
        $model->delete();
        app(AuditLogger::class)->record('content.deleted', $model, ['type' => $model->getMorphClass(), 'recycle_bin' => $this->usesRecycleBin()], actor: $editor);
    }

    /**
     * The complete save workflow, in one transaction.
     *
     * @param  TModel|null  $model
     * @param  array<string, mixed>  $data  validated input
     * @return TModel
     *
     * @throws ValidationException
     */
    public function save(?Model $model, array $data, Request $request, User $editor): Model
    {
        $isNew = $model === null;
        $model ??= $this->newModel();

        try {
            return DB::transaction(function () use ($model, $data, $request, $editor, $isNew) {
                foreach ($this->fields($model) as $field) {
                    $field->fill($model, $data);
                }

                $this->beforeSave($model, $data, $request);

                $transition = null;
                if ($this->isPublishable()) {
                    /** @var PublicationStatus $current */
                    $current = $isNew ? PublicationStatus::Draft : $model->getAttribute('status');
                    $target = isset($data['status']) ? PublicationStatus::from($data['status']) : $current;
                    $publishAt = array_key_exists('publish_at', $data) ? SiteTime::fromInput($data['publish_at']) : $model->getAttribute('publish_at');
                    $expiresAt = array_key_exists('expires_at', $data) ? SiteTime::fromInput($data['expires_at']) : $model->getAttribute('expires_at');
                    $transition = app(PublicationService::class)->apply($model, $target, $publishAt, $expiresAt);
                }

                $changed = array_keys($model->getDirty());
                $model->save();

                foreach ($this->fields($model) as $field) {
                    $field->afterSave($model, $data);
                }

                if ($model instanceof Routable) {
                    $path = trim((string) ($data['public_path'] ?? ''));
                    if ($path === '' && $model->getAttribute('canonicalRoute') === null) {
                        $path = app(RouteManager::class)->suggestPath($model);
                    }
                    if ($path !== '') {
                        app(RouteManager::class)->assign($model, $path);
                    }
                }

                if ($model instanceof Revisionable) {
                    app(RevisionService::class)->record($model, $editor, $data['revision_summary'] ?? null);
                }

                $audit = app(AuditLogger::class);
                $audit->record($isNew ? 'content.created' : 'content.updated', $model, [
                    'type' => $model->getMorphClass(),
                    'changed' => array_values(array_diff($changed, ['updated_at', 'created_at', 'updated_by', 'created_by'])),
                ], actor: $editor);
                if ($transition !== null) {
                    $audit->record($transition, $model, ['type' => $model->getMorphClass()], actor: $editor);
                }

                return $model;
            });
        } catch (Throwable $e) {
            foreach ($this->onFailure as $cleanup) {
                $cleanup();
            }

            throw $e instanceof DomainRuleViolation ? $e->toValidationException() : $e;
        } finally {
            $this->onFailure = [];
        }
    }
}
