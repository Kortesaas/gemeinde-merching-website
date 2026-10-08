<?php

namespace App\Http\Controllers\Admin;

use App\Admin\Options;
use App\Admin\ResourceRegistry;
use App\Contracts\Revisionable;
use App\Http\Controllers\Controller;
use App\Models\ExternalResource;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Content\RevisionService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Places documents and managed links on a record: validated slot, optional
 * group heading (e.g. "2026") and sort order. A document/link is maintained
 * once and can be placed in many places; removing a placement never deletes
 * the document or its file.
 */
class PlacementController extends Controller
{
    private const KINDS = ['documents' => 'documentSlots', 'links' => 'resourceSlots'];

    public function store(Request $request, int $record): RedirectResponse
    {
        [$model, $relation, $slots] = $this->owner($request, $record);

        $data = $request->validate([
            'kind' => ['required', Rule::in(array_keys(self::KINDS))],
            'item_id' => ['required', 'integer', Rule::in(array_keys($this->items((string) $request->input('kind'))))],
            'slot' => ['required', 'string', Rule::in(array_keys($slots))],
            'group_label' => ['nullable', 'string', 'max:120'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ], [], ['item_id' => 'Eintrag', 'slot' => 'Bereich', 'group_label' => 'Gruppe', 'sort_order' => 'Reihenfolge']);

        if ($relation->wherePivot('slot', $data['slot'])->where($relation->getRelated()->getQualifiedKeyName(), $data['item_id'])->exists()) {
            throw ValidationException::withMessages(['item_id' => 'Dieser Eintrag ist in diesem Bereich bereits zugeordnet.']);
        }

        DB::transaction(function () use ($model, $request, $data) {
            [, $relation] = $this->owner($request, (int) $model->getKey());
            $relation->attach((int) $data['item_id'], [
                'slot' => $data['slot'],
                'group_label' => $data['group_label'] ?? null,
                'sort_order' => (int) ($data['sort_order'] ?? 0),
            ]);
            $this->recordChange($request, $model, 'placement_added');
        });

        return back()->with('status', 'Zuordnung wurde hinzugefügt.');
    }

    public function update(Request $request, int $record, string $kind, int $pivot): RedirectResponse
    {
        [$model, $relation] = $this->owner($request, $record, $kind);

        $data = $request->validate([
            'group_label' => ['nullable', 'string', 'max:120'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
        ], [], ['group_label' => 'Gruppe', 'sort_order' => 'Reihenfolge']);

        DB::transaction(function () use ($relation, $pivot, $data, $request, $model) {
            $updated = $relation->newPivotStatement()->where('id', $pivot)
                ->where($relation->getForeignPivotKeyName(), $model->getKey())
                ->update(['group_label' => $data['group_label'] ?? null, 'sort_order' => (int) $data['sort_order'], 'updated_at' => now()]);
            abort_if($updated === 0, 404);
            $this->recordChange($request, $model, 'placement_updated');
        });

        return back()->with('status', 'Zuordnung wurde aktualisiert.');
    }

    public function destroy(Request $request, int $record, string $kind, int $pivot): RedirectResponse
    {
        [$model, $relation] = $this->owner($request, $record, $kind);

        DB::transaction(function () use ($relation, $pivot, $request, $model) {
            $deleted = $relation->newPivotStatement()->where('id', $pivot)
                ->where($relation->getForeignPivotKeyName(), $model->getKey())->delete();
            abort_if($deleted === 0, 404);
            $this->recordChange($request, $model, 'placement_removed');
        });

        return back()->with('status', 'Zuordnung wurde entfernt. Das Dokument bzw. der Link selbst bleibt erhalten.');
    }

    /**
     * @return array{0: Model, 1: BelongsToMany<Model, Model>, 2: array<string, string>}
     */
    private function owner(Request $request, int $record, ?string $kind = null): array
    {
        $resource = ResourceRegistry::get((string) $request->route()?->defaults['resource']);
        $model = $resource->find($record);
        Gate::authorize('update', $model);

        $kind ??= (string) $request->input('kind', 'documents');
        abort_unless(isset(self::KINDS[$kind]) && method_exists($model, self::KINDS[$kind]), 404);

        $relation = $kind === 'documents' ? $model->documents() : $model->externalResources(); // @phpstan-ignore method.notFound, method.notFound

        /** @var array<string, string> $slots */
        $slots = (new \ReflectionMethod($model, self::KINDS[$kind]))->invoke(null);

        return [$model, $relation, $slots];
    }

    /**
     * @return array<int, string>
     */
    private function items(string $kind): array
    {
        return $kind === 'links'
            ? ExternalResource::query()->orderBy('title')->pluck('title', 'id')->all()
            : Options::documents();
    }

    private function recordChange(Request $request, Model $model, string $what): void
    {
        /** @var User $user */
        $user = $request->user();
        if ($model instanceof Revisionable) {
            app(RevisionService::class)->record($model, $user, 'Zuordnungen geändert');
        }
        app(AuditLogger::class)->record('content.updated', $model, ['type' => $model->getMorphClass(), 'change' => $what], actor: $user);
    }
}
