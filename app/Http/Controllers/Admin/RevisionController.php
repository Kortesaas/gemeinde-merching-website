<?php

namespace App\Http\Controllers\Admin;

use App\Admin\ContentResource;
use App\Admin\ResourceRegistry;
use App\Contracts\Revisionable;
use App\Exceptions\DomainRuleViolation;
use App\Http\Controllers\Controller;
use App\Models\ContentRevision;
use App\Models\User;
use App\Services\Content\RevisionService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class RevisionController extends Controller
{
    public function __construct(private readonly RevisionService $revisions) {}

    public function index(Request $request, int $record): View
    {
        [$resource, $model] = $this->owner($request, $record);
        Gate::authorize('viewRevisions', $model);

        return view('admin.resources.revisions', [
            'resource' => $resource,
            'model' => $model,
            'revisions' => $this->revisions->list($model),
        ]);
    }

    public function show(Request $request, int $record, int $revision): View
    {
        [$resource, $model] = $this->owner($request, $record);
        Gate::authorize('viewRevisions', $model);

        return view('admin.resources.revision', [
            'resource' => $resource,
            'model' => $model,
            'revision' => $this->find($model, $revision),
            'labels' => collect($resource->formFields($model))->mapWithKeys(fn ($f) => [$f->name => $f->label])->all(),
        ]);
    }

    public function restore(Request $request, int $record, int $revision): RedirectResponse
    {
        [$resource, $model] = $this->owner($request, $record);
        Gate::authorize('restoreRevision', $model);

        /** @var User $user */
        $user = $request->user();
        try {
            $new = $this->revisions->restore($this->find($model, $revision), $user);
        } catch (DomainRuleViolation $e) {
            throw $e->toValidationException();
        }

        return redirect()->route("admin.{$resource->key()}.edit", $model->getKey())
            ->with('status', "Version {$revision} wurde wiederhergestellt (neue Version {$new->revision_number}).");
    }

    /**
     * @return array{0: ContentResource<Model>, 1: Model&Revisionable}
     */
    private function owner(Request $request, int $record): array
    {
        $resource = ResourceRegistry::get((string) $request->route()?->defaults['resource']);
        $model = $resource->find($record);
        abort_unless($model instanceof Revisionable, 404);

        return [$resource, $model];
    }

    private function find(Model $model, int $number): ContentRevision
    {
        return ContentRevision::query()
            ->where('revisionable_type', $model->getMorphClass())
            ->where('revisionable_id', $model->getKey())
            ->where('revision_number', $number)
            ->firstOrFail();
    }
}
