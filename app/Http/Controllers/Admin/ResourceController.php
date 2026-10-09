<?php

namespace App\Http\Controllers\Admin;

use App\Admin\ContentResource;
use App\Admin\ResourceRegistry;
use App\Enums\PublicationStatus;
use App\Exceptions\DomainRuleViolation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ResourceRequest;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Generic functional CRUD for all admin resources (minimal UI; the final CMS
 * design follows later). Every action is authorized through the policies.
 */
class ResourceController extends Controller
{
    public function index(Request $request): View
    {
        $resource = $this->resource($request);
        Gate::authorize('viewAny', $resource->model());

        $trash = $request->boolean('papierkorb') && $resource->usesRecycleBin();
        $query = $resource->query()->latest('updated_at');

        if ($trash) {
            $query->onlyTrashed(); // @phpstan-ignore method.notFound
        }

        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($resource, $search) {
                foreach ($resource->searchColumns() as $column) {
                    $q->orWhere($column, 'like', '%'.addcslashes($search, '%_\\').'%');
                }
            });
        }

        $status = PublicationStatus::tryFrom((string) $request->query('status', ''));
        if ($status !== null && $resource->isPublishable()) {
            $query->where('status', $status->value);
        }

        return view('admin.resources.index', [
            'resource' => $resource,
            'records' => $query->paginate(25)->withQueryString(),
            'trash' => $trash,
            'search' => $search,
            'status' => $status,
        ]);
    }

    public function create(Request $request): View
    {
        $resource = $this->resource($request);
        Gate::authorize('create', $resource->model());

        return $this->form($resource, $resource->newModel());
    }

    public function store(ResourceRequest $request): RedirectResponse
    {
        $resource = $request->resource();
        $model = $resource->save(null, $request->validated(), $request, $this->user($request));

        return redirect()->route("admin.{$resource->key()}.edit", $model->getKey())
            ->with('status', "{$resource->label()} wurde angelegt.");
    }

    public function edit(Request $request, int $record): View
    {
        $resource = $this->resource($request);
        $model = $resource->find($record);
        Gate::authorize('view', $model);

        return $this->form($resource, $model);
    }

    public function update(ResourceRequest $request, int $record): RedirectResponse
    {
        $resource = $request->resource();
        $model = $resource->save($request->record(), $request->validated(), $request, $this->user($request));

        return redirect()->route("admin.{$resource->key()}.edit", $model->getKey())
            ->with('status', 'Änderungen wurden gespeichert.');
    }

    public function destroy(Request $request, int $record): RedirectResponse
    {
        $resource = $this->resource($request);
        $model = $resource->find($record);
        Gate::authorize('delete', $model);

        try {
            $resource->delete($model, $this->user($request));
        } catch (DomainRuleViolation $e) {
            throw $e->toValidationException();
        }

        return redirect()->route("admin.{$resource->key()}.index")->with('status', $resource->usesRecycleBin()
            ? "„{$this->title($model)}“ wurde in den Papierkorb gelegt."
            : "„{$this->title($model)}“ wurde gelöscht.");
    }

    public function restore(Request $request, int $record): RedirectResponse
    {
        $resource = $this->resource($request);
        $model = $resource->find($record);
        Gate::authorize('restore', $model);

        $model->restore(); // @phpstan-ignore method.notFound (recycle-bin types use SoftDeletes)
        app(AuditLogger::class)->record('content.restored', $model, ['type' => $model->getMorphClass()]);

        return redirect()->route("admin.{$resource->key()}.edit", $model->getKey())
            ->with('status', "„{$this->title($model)}“ wurde wiederhergestellt.");
    }

    public function forceDelete(Request $request, int $record): RedirectResponse
    {
        $resource = $this->resource($request);
        $model = $resource->find($record);
        Gate::authorize('forceDelete', $model);

        try {
            DB::transaction(function () use ($resource, $model) {
                $resource->beforeForceDelete($model);
                $model->forceDelete();
                $resource->afterForceDelete($model);
                app(AuditLogger::class)->record('content.force_deleted', null, ['type' => $model->getMorphClass(), 'id' => $model->getKey()]);
            });
        } catch (DomainRuleViolation $e) {
            return back()->withErrors(['general' => $e->getMessage()]);
        } catch (QueryException $e) {
            if ($e->getCode() !== '23000') {
                throw $e;
            }

            return back()->withErrors(['general' => 'Der Eintrag wird noch von anderen Inhalten verwendet und kann nicht endgültig gelöscht werden.']);
        }

        return redirect()->route("admin.{$resource->key()}.index", ['papierkorb' => 1])
            ->with('status', "„{$this->title($model)}“ wurde endgültig gelöscht.");
    }

    /**
     * @param  ContentResource<Model>  $resource
     */
    private function form(ContentResource $resource, Model $model): View
    {
        $user = request()->user();
        $editable = $model->exists ? (bool) $user?->can('update', $model) : true;

        return view('admin.resources.form', [
            'resource' => $resource,
            'model' => $model,
            'fields' => $resource->formFields($model),
            'editable' => $editable,
            'canPublish' => $resource->isPublishable() && (bool) $user?->can('publish', $model->exists ? $model : $resource->model()),
            'canArchive' => $resource->isPublishable() && (bool) $user?->can('archive', $model->exists ? $model : $resource->model()),
        ]);
    }

    /**
     * @return ContentResource<Model>
     */
    private function resource(Request $request): ContentResource
    {
        return ResourceRegistry::get((string) $request->route()?->defaults['resource']);
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }

    private function title(Model $model): string
    {
        return method_exists($model, 'displayTitle') ? $model->displayTitle() : '#'.$model->getKey();
    }
}
