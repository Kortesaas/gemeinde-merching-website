<?php

namespace App\Http\Controllers\Admin;

use App\Admin\Options;
use App\Admin\ResourceRegistry;
use App\Contracts\Proposable;
use App\Enums\ProposalStatus;
use App\Exceptions\DomainRuleViolation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProposalRequest;
use App\Models\ContentProposal;
use App\Models\ExternalResource;
use App\Models\User;
use App\Services\Content\ProposalDiff;
use App\Services\Content\ProposalService;
use App\Support\Authorization\Ability;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Change proposals ("Änderungsvorschläge") and the review queue
 * ("Freigaben"). The live record is only changed by apply().
 */
class ProposalController extends Controller
{
    private const KINDS = ['documents' => ['relation' => 'documents', 'slots' => 'documentSlots'], 'links' => ['relation' => 'externalResources', 'slots' => 'resourceSlots']];

    public function __construct(private readonly ProposalService $proposals) {}

    /**
     * Review queue and the user's own proposals.
     */
    public function index(Request $request): View
    {
        $user = $this->user($request);

        $toReview = ContentProposal::query()->where('status', ProposalStatus::Submitted->value)
            ->with(['author', 'proposable'])->orderBy('submitted_at')->get()
            ->filter(fn (ContentProposal $p) => $user->can('review', $p))->values();

        $mine = ContentProposal::query()->where('author_id', $user->getKey())
            ->with('proposable')->latest('id')->limit(50)->get();

        return view('admin.proposals.index', ['toReview' => $toReview, 'mine' => $mine]);
    }

    /**
     * Start a proposal for a published record (or continue the user's open one).
     */
    public function store(Request $request, int $record): RedirectResponse
    {
        $resource = ResourceRegistry::get((string) $request->route()?->defaults['resource']);
        $model = $resource->find($record);
        Gate::authorize('propose', $model);
        /** @var Model&Proposable $model */
        $existing = $model->proposals()->open()->where('author_id', $this->user($request)->getKey())->first();

        $proposal = $existing ?? $this->proposals->create($model, $this->user($request));

        return redirect()->route('admin.proposals.show', $proposal)
            ->with('status', $existing ? 'Sie haben bereits einen offenen Vorschlag zu diesem Inhalt.' : 'Änderungsvorschlag angelegt. Der veröffentlichte Inhalt bleibt unverändert, bis der Vorschlag freigegeben wird.');
    }

    public function show(Request $request, ContentProposal $proposal, ProposalDiff $diff): View
    {
        Gate::authorize('view', $proposal);
        $record = $proposal->proposable;
        abort_unless($record instanceof Proposable, 404);
        $resource = ResourceRegistry::forModel($record) ?? abort(404);

        $kinds = [];
        foreach (self::KINDS as $kind => $config) {
            if (method_exists($record, $config['slots'])) {
                $kinds[$kind] = [
                    'label' => $kind === 'documents' ? 'Dokumente' : 'Links & Online-Dienste',
                    'slots' => (new \ReflectionMethod($record, $config['slots']))->invoke(null),
                    'rows' => (array) ($proposal->payload['relations'][$config['relation']] ?? []),
                    'options' => $kind === 'documents' ? Options::documents() : Options::externalResources(),
                ];
            }
        }

        return view('admin.proposals.show', [
            'proposal' => $proposal,
            'record' => $record,
            'resource' => $resource,
            'fields' => $resource->formFields($record),
            'preview' => $diff->preview($record, $proposal->payload),
            'diff' => $diff->rows($proposal, $resource, $record),
            'conflicts' => $proposal->status === ProposalStatus::Submitted ? $this->proposals->conflicts($proposal) : [],
            'kinds' => $kinds,
        ]);
    }

    public function update(ProposalRequest $request, ContentProposal $proposal): RedirectResponse
    {
        $user = $this->user($request);
        $data = $request->validated();

        $submitting = ($data['action'] ?? 'save') === 'submit';
        if ($submitting) {
            Gate::authorize('submit', $proposal);
        }

        try {
            $this->proposals->update($proposal, $request->resource(), $data, $request, $user);
            if ($submitting) {
                $this->proposals->submit($proposal->refresh(), $user);

                return redirect()->route('admin.proposals.show', $proposal)->with('status', 'Der Vorschlag wurde zur Prüfung eingereicht.');
            }
        } catch (DomainRuleViolation $e) {
            throw $e->toValidationException();
        }

        return redirect()->route('admin.proposals.show', $proposal)->with('status', 'Vorschlag gespeichert. Er ist noch nicht eingereicht.');
    }

    public function submit(Request $request, ContentProposal $proposal): RedirectResponse
    {
        Gate::authorize('submit', $proposal);

        return $this->attempt(fn () => $this->proposals->submit($proposal, $this->user($request)), $proposal, 'Der Vorschlag wurde zur Prüfung eingereicht.');
    }

    public function withdraw(Request $request, ContentProposal $proposal): RedirectResponse
    {
        Gate::authorize('withdraw', $proposal);

        return $this->attempt(fn () => $this->proposals->withdraw($proposal, $this->user($request)), $proposal, 'Der Vorschlag wurde zurückgezogen.');
    }

    public function apply(Request $request, ContentProposal $proposal): RedirectResponse
    {
        Gate::authorize('apply', $proposal);
        $confirm = $request->boolean('confirm_conflicts');

        return $this->attempt(
            fn () => $this->proposals->apply($proposal, $this->user($request), $confirm),
            $proposal,
            'Der Vorschlag wurde freigegeben und ist jetzt veröffentlicht.',
        );
    }

    public function reject(Request $request, ContentProposal $proposal): RedirectResponse
    {
        Gate::authorize('review', $proposal);
        $data = $request->validate(['review_comment' => ['required', 'string', 'max:2000']], [], ['review_comment' => 'Begründung']);

        return $this->attempt(fn () => $this->proposals->reject($proposal, $this->user($request), $data['review_comment']), $proposal, 'Der Vorschlag wurde abgelehnt.');
    }

    public function storePlacement(Request $request, ContentProposal $proposal): RedirectResponse
    {
        Gate::authorize('update', $proposal);
        [$relation, $slots, $rows] = $this->placementContext($proposal, (string) $request->input('kind'));
        $itemIds = $request->input('kind') === 'links'
            ? ExternalResource::query()->pluck('id')->all()
            : array_keys(Options::documents());

        $data = $request->validate([
            'kind' => ['required', Rule::in(array_keys(self::KINDS))],
            'item_id' => ['required', 'integer', Rule::in($itemIds)],
            'slot' => ['required', 'string', Rule::in(array_keys($slots))],
            'group_label' => ['nullable', 'string', 'max:120'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ], [], ['item_id' => 'Eintrag', 'slot' => 'Bereich', 'group_label' => 'Gruppe', 'sort_order' => 'Reihenfolge']);

        foreach ($rows as $row) {
            if ((int) $row['id'] === (int) $data['item_id'] && $row['slot'] === $data['slot']) {
                return back()->withErrors(['item_id' => 'Dieser Eintrag ist in diesem Bereich bereits zugeordnet.']);
            }
        }

        $rows[] = ['id' => (int) $data['item_id'], 'slot' => $data['slot'], 'group_label' => $data['group_label'] ?? null, 'sort_order' => (int) ($data['sort_order'] ?? 0)];
        $this->proposals->updatePlacements($proposal, $relation, $rows, $this->user($request));

        return back()->with('status', 'Zuordnung im Vorschlag hinzugefügt.');
    }

    public function destroyPlacement(Request $request, ContentProposal $proposal, string $kind, int $index): RedirectResponse
    {
        Gate::authorize('update', $proposal);
        [$relation, , $rows] = $this->placementContext($proposal, $kind);
        abort_unless(isset($rows[$index]), 404);

        unset($rows[$index]);
        $this->proposals->updatePlacements($proposal, $relation, array_values($rows), $this->user($request));

        return back()->with('status', 'Zuordnung im Vorschlag entfernt.');
    }

    /**
     * @return array{0: string, 1: array<string, string>, 2: list<array<string, mixed>>}
     */
    private function placementContext(ContentProposal $proposal, string $kind): array
    {
        $record = $proposal->proposable;
        abort_unless(isset(self::KINDS[$kind]) && $record !== null && method_exists($record, self::KINDS[$kind]['slots']), 404);
        $relation = self::KINDS[$kind]['relation'];

        /** @var array<string, string> $slots */
        $slots = (new \ReflectionMethod($record, self::KINDS[$kind]['slots']))->invoke(null);

        return [$relation, $slots, array_values((array) ($proposal->payload['relations'][$relation] ?? []))];
    }

    private function attempt(callable $action, ContentProposal $proposal, string $message): RedirectResponse
    {
        try {
            $action();
        } catch (DomainRuleViolation $e) {
            return back()->withErrors([$e->field => $e->getMessage()]);
        }

        return redirect()->route('admin.proposals.show', $proposal)->with('status', $message);
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }

    /**
     * Whether the user may review proposals of any content type (dashboard link).
     */
    public static function canReviewAnything(User $user): bool
    {
        foreach (ResourceRegistry::all() as $resource) {
            if ($resource->type()->isPublishable() && $user->can($resource->type()->permission(Ability::Publish))) {
                return true;
            }
        }

        return false;
    }
}
