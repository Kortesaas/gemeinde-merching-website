<?php

namespace App\Admin;

use App\Admin\Fields\Field;
use App\Admin\Fields\Lines;
use App\Contracts\Revisionable;
use App\Contracts\Routable;
use App\Enums\PublicationStatus;
use App\Exceptions\DomainRuleViolation;
use App\Models\BudgetPlan;
use App\Models\Media;
use App\Models\User;
use App\Rules\SiteDateTime;
use App\Services\Audit\AuditLogger;
use App\Services\Content\BudgetWorkflow;
use App\Services\Content\PublicationService;
use App\Services\Content\ReferenceProtection;
use App\Services\Content\RevisionService;
use App\Services\Quality\QualityChecks;
use App\Services\Routing\RouteManager;
use App\Services\Search\SearchIndexer;
use App\Support\Authorization\ContentType;
use App\Support\SiteTime;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
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

    /** True while form data is applied to compute a change proposal (no side effects such as file uploads). */
    protected bool $applyingProposal = false;

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

    /**
     * @param  TModel|null  $model
     * @return list<Field>
     */
    public function formFields(?Model $model): array
    {
        $fields = $this->fields($model);
        if ($this->isRoutable()) {
            $fields[] = Fields\Text::make('seo_title', 'Seitentitel (optional)');
            $fields[] = Fields\Textarea::make('meta_description', 'Meta-Beschreibung')->rules(['max:500']);
            $fields[] = Fields\Checkbox::make('seo_noindex', 'Von Suchmaschinen ausschließen');
        }
        if (method_exists($this->model(), 'media')) {
            $fields[] = Fields\BelongsToMany::make('media', 'Medien')->options(fn () => Media::query()->pluck('title', 'id')->all())->sortable();
        }

        if (method_exists($this->model(), 'blocks')) {
            $fields[] = Fields\Rows::make('blocks', 'Inhaltsbausteine')->definition('blocks');
        }

        return $fields;
    }

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
    public function beforeForceDelete(Model $model): void
    {
        app(ReferenceProtection::class)->guard($model);
    }

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
        $rules = ['_form_started' => ['nullable'], '_form_complete' => ['required_with:_form_started', 'in:1']];
        foreach ($this->formFields($model) as $field) {
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

        foreach ($this->formFields($model) as $field) {
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
     * Apply form data to a model (fields, derived values, relations) without
     * publication changes, routes, revisions or audit. Used to compute change
     * proposals inside a rolled-back transaction.
     *
     * @param  TModel  $model
     * @param  array<string, mixed>  $data
     */
    public function applyFormData(Model $model, array $data, Request $request, bool $forProposal = false): void
    {
        $this->applyingProposal = $forProposal;

        try {
            foreach ($this->formFields($model) as $field) {
                $field->fill($model, $data);
            }
            $this->beforeSave($model, $data, $request);
            $model->save();
            foreach ($this->formFields($model) as $field) {
                $field->afterSave($model, $data);
            }
        } finally {
            $this->applyingProposal = false;
        }
    }

    /**
     * URL handling on save:
     * - an entered path is assigned (former paths keep redirecting);
     * - existing routes – e.g. migrated legacy URLs – are never changed
     *   automatically, so they always take precedence over new conventions;
     * - new records of auto-route types get a suggested slashless path;
     * - for opt-in types (documents, departments, locations, organizations)
     *   an empty path means "no public page" and withdraws an existing one.
     *
     * @param  array<string, mixed>  $data
     */
    protected function syncPublicRoute(Model&Routable $model, array $data): void
    {
        $routes = app(RouteManager::class);
        $path = trim((string) ($data['public_path'] ?? ''));
        $hasRoute = $model->canonicalRoute()->exists();

        if ($path !== '') {
            $routes->assign($model, $path);
        } elseif (! $hasRoute && $model::createsRouteAutomatically()) {
            $routes->assign($model, $routes->suggestPath($model));
        } elseif ($hasRoute && ! $model::createsRouteAutomatically() && array_key_exists('public_path', $data)) {
            $routes->deactivate($model);
        }
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
            return app(BudgetWorkflow::class)->transaction(function () use ($model, $data, $request, $editor, $isNew) {
                app(BudgetWorkflow::class)->lock($model);
                if ($model instanceof BudgetPlan && ! $isNew) {
                    Gate::forUser($editor)->authorize('update', $model);
                }
                foreach ($this->formFields($model) as $field) {
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

                foreach ($this->formFields($model) as $field) {
                    $field->afterSave($model, $data);
                }

                if ($model instanceof Routable) {
                    $this->syncPublicRoute($model, $data);
                }

                app(BudgetWorkflow::class)->finish($model, $editor, in_array($transition, ['content.published', 'content.unarchived'], true));
                app(QualityChecks::class)->enforcePublicAssets($model);
                app(SearchIndexer::class)->sync($model);

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
