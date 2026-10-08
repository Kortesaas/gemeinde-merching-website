<?php

namespace App\Services\Content;

use App\Admin\ContentResource;
use App\Contracts\Proposable;
use App\Enums\ProposalStatus;
use App\Exceptions\DomainRuleViolation;
use App\Models\ContentProposal;
use App\Models\ContentRevision;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Change proposals for published content ("Änderungsvorschläge").
 *
 * - The live record is never modified while a proposal is drafted or in
 *   review; the public site keeps showing the published version.
 * - The proposed state is computed by running the normal form logic of the
 *   admin resource inside a database transaction that is always rolled back,
 *   so proposals behave exactly like direct edits (validation, relations,
 *   derived values) without side effects.
 * - Applying (publish permission, by default not the author) writes only the
 *   parts the proposal changed relative to its base, records a revision and
 *   audits the decision. Concurrent live changes to the same parts are
 *   reported as conflicts and must be confirmed explicitly.
 */
class ProposalService
{
    public function __construct(
        private readonly RevisionService $revisions,
        private readonly AuditLogger $audit,
    ) {}

    public function create(Model&Proposable $record, User $author): ContentProposal
    {
        return DB::transaction(function () use ($record, $author) {
            // Make sure the base state exists as a revision.
            $base = $record->revisions()->first() ?? $this->revisions->record($record, null, 'Ausgangsstand');
            $snapshot = $this->revisions->snapshot($record);

            $proposal = ContentProposal::create([
                'proposable_type' => $record->getMorphClass(),
                'proposable_id' => $record->getKey(),
                'status' => ProposalStatus::Draft,
                'author_id' => $author->getKey(),
                'base_revision_number' => $base?->revision_number,
                'base_snapshot' => $snapshot,
                'payload' => $snapshot,
            ]);

            $this->audit->record('proposal.created', $record, ['proposal' => $proposal->id], actor: $author);

            return $proposal;
        });
    }

    /**
     * Update the proposed state from validated form data.
     *
     * @param  ContentResource<Model>  $resource
     * @param  array<string, mixed>  $data
     */
    public function update(ContentProposal $proposal, ContentResource $resource, array $data, Request $request, User $author): void
    {
        $record = $this->record($proposal);
        $payload = $this->computePayload($resource, $record, $proposal->payload, $data, $request);

        $proposal->update([
            'payload' => $payload,
            'summary' => isset($data['proposal_summary']) ? mb_substr((string) $data['proposal_summary'], 0, 255) : $proposal->summary,
        ]);

        $this->audit->record('proposal.updated', $record, ['proposal' => $proposal->id, 'changed' => $this->changes($proposal)], actor: $author);
    }

    /**
     * Replace the placement rows of a relation in the payload (documents/links).
     *
     * @param  list<array<string, mixed>>  $rows
     */
    public function updatePlacements(ContentProposal $proposal, string $relation, array $rows, User $author): void
    {
        $payload = $proposal->payload;
        $payload['relations'][$relation] = $rows;
        $proposal->update(['payload' => $payload]);

        $this->audit->record('proposal.updated', $this->record($proposal), ['proposal' => $proposal->id, 'change' => 'placements'], actor: $author);
    }

    public function submit(ContentProposal $proposal, User $author): void
    {
        $this->assertStatus($proposal, ProposalStatus::Draft);

        if ($this->changes($proposal) === []) {
            throw new DomainRuleViolation('Der Vorschlag enthält noch keine Änderungen.');
        }

        $proposal->update(['status' => ProposalStatus::Submitted, 'submitted_at' => now()]);
        $this->audit->record('proposal.submitted', $this->record($proposal), ['proposal' => $proposal->id], actor: $author);
    }

    public function withdraw(ContentProposal $proposal, User $author): void
    {
        if (! $proposal->status->isOpen()) {
            throw new DomainRuleViolation('Nur offene Vorschläge können zurückgezogen werden.');
        }

        $proposal->update(['status' => ProposalStatus::Withdrawn]);
        $this->audit->record('proposal.withdrawn', $this->record($proposal), ['proposal' => $proposal->id], actor: $author);
    }

    public function reject(ContentProposal $proposal, User $reviewer, string $comment): void
    {
        $this->assertStatus($proposal, ProposalStatus::Submitted);

        $proposal->update([
            'status' => ProposalStatus::Rejected,
            'reviewer_id' => $reviewer->getKey(),
            'reviewed_at' => now(),
            'review_comment' => $comment,
        ]);
        $this->audit->record('proposal.rejected', $this->record($proposal), ['proposal' => $proposal->id], actor: $reviewer);
    }

    /**
     * Apply the changed parts to the live record and publish them.
     *
     * @throws DomainRuleViolation
     */
    public function apply(ContentProposal $proposal, User $reviewer, bool $confirmConflicts): ContentRevision
    {
        $this->assertStatus($proposal, ProposalStatus::Submitted);
        $record = $this->record($proposal);

        $changes = $this->changedParts($proposal->base_snapshot, $proposal->payload);
        if ($changes === ['attributes' => [], 'relations' => [], 'collections' => []]) {
            throw new DomainRuleViolation('Der Vorschlag enthält keine Änderungen.');
        }

        $conflicts = $this->conflicts($proposal);
        if ($conflicts !== [] && ! $confirmConflicts) {
            throw new DomainRuleViolation(
                'Der veröffentlichte Inhalt wurde seit dem Vorschlag an denselben Stellen geändert. '
                .'Bitte prüfen Sie die Konflikte und bestätigen Sie die Übernahme ausdrücklich.',
                'confirm_conflicts',
            );
        }

        try {
            return DB::transaction(function () use ($proposal, $record, $reviewer, $changes, $conflicts) {
                $this->revisions->applySnapshot($record, $proposal->payload, $changes['attributes'], $changes['relations'], $changes['collections']);

                $author = $proposal->author !== null ? $proposal->author->name : 'unbekannt';
                $revision = $this->revisions->record($record->refresh(), $reviewer, "Änderungsvorschlag #{$proposal->id} von {$author} übernommen"
                    .($proposal->summary ? ': '.$proposal->summary : ''))
                    ?? $record->revisions()->firstOrFail();

                $proposal->update([
                    'status' => ProposalStatus::Applied,
                    'reviewer_id' => $reviewer->getKey(),
                    'reviewed_at' => now(),
                    'applied_revision_number' => $revision->revision_number,
                ]);

                $this->audit->record('proposal.applied', $record, [
                    'proposal' => $proposal->id,
                    'revision' => $revision->revision_number,
                    'changed' => [...$changes['attributes'], ...$changes['relations'], ...$changes['collections']],
                    'conflicts_overridden' => array_keys($conflicts),
                ], actor: $reviewer);

                return $revision;
            });
        } catch (QueryException $e) {
            if ($e->getCode() !== '23000') {
                throw $e;
            }

            throw new DomainRuleViolation('Der Vorschlag verweist auf einen Eintrag, der inzwischen gelöscht wurde. Bitte den Vorschlag überarbeiten.');
        }
    }

    /**
     * Names of all attributes/relations/collections the proposal changes.
     *
     * @return list<string>
     */
    public function changes(ContentProposal $proposal): array
    {
        $parts = $this->changedParts($proposal->base_snapshot, $proposal->payload);

        return [...$parts['attributes'], ...$parts['relations'], ...$parts['collections']];
    }

    /**
     * Parts changed by the proposal AND changed differently in the live record
     * since the proposal was created: name => current live value.
     *
     * @return array<string, mixed>
     */
    public function conflicts(ContentProposal $proposal): array
    {
        $live = $this->revisions->snapshot($this->record($proposal));
        $conflicts = [];

        foreach (['attributes', 'relations', 'collections'] as $section) {
            foreach ($this->changedParts($proposal->base_snapshot, $proposal->payload)[$section] as $name) {
                $base = self::canonical($proposal->base_snapshot[$section][$name] ?? null);
                $now = self::canonical($live[$section][$name] ?? null);
                $proposed = self::canonical($proposal->payload[$section][$name] ?? null);

                if ($now !== $base && $now !== $proposed) {
                    $conflicts[$name] = $live[$section][$name] ?? null;
                }
            }
        }

        return $conflicts;
    }

    /**
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $payload
     * @return array{attributes: list<string>, relations: list<string>, collections: list<string>}
     */
    public function changedParts(array $base, array $payload): array
    {
        $parts = ['attributes' => [], 'relations' => [], 'collections' => []];

        foreach (array_keys($parts) as $section) {
            $names = array_unique([...array_keys((array) ($base[$section] ?? [])), ...array_keys((array) ($payload[$section] ?? []))]);
            foreach ($names as $name) {
                if (in_array($name, RevisionService::PUBLICATION_FIELDS, true)) {
                    continue;
                }
                if (self::canonical($base[$section][$name] ?? null) !== self::canonical($payload[$section][$name] ?? null)) {
                    $parts[$section][] = (string) $name;
                }
            }
        }

        return $parts;
    }

    /**
     * Runs the resource's form logic on the record inside a transaction that
     * is always rolled back and returns the resulting snapshot.
     *
     * @param  ContentResource<Model>  $resource
     * @param  array<string, mixed>  $startFrom  current proposal payload (keeps e.g. placements)
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function computePayload(ContentResource $resource, Model&Proposable $record, array $startFrom, array $data, Request $request): array
    {
        $connection = $record->getConnection();
        $connection->beginTransaction();

        try {
            /** @var Model&Proposable $working */
            $working = $resource->find((int) $record->getKey());
            $this->revisions->applySnapshot($working, $startFrom);
            $resource->applyFormData($working, $data, $request, forProposal: true);

            return $this->revisions->snapshot($working);
        } finally {
            $connection->rollBack();
        }
    }

    private function record(ContentProposal $proposal): Model&Proposable
    {
        $record = $proposal->proposable;
        if (! $record instanceof Proposable) {
            throw new DomainRuleViolation('Der zugehörige Inhalt existiert nicht mehr.');
        }

        return $record;
    }

    private function assertStatus(ContentProposal $proposal, ProposalStatus $expected): void
    {
        if ($proposal->status !== $expected) {
            throw new DomainRuleViolation("Der Vorschlag hat den Status „{$proposal->status->label()}“.");
        }
    }

    private static function canonical(mixed $value): string
    {
        $normalize = function (mixed $v) use (&$normalize): mixed {
            if (! is_array($v)) {
                return is_bool($v) ? (int) $v : (is_numeric($v) && ! is_string($v) ? (string) $v : $v);
            }
            if (! array_is_list($v)) {
                ksort($v);
            }

            return array_map($normalize, $v);
        };

        return (string) json_encode($normalize($value));
    }
}
