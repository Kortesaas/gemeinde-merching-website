<?php

namespace App\Services\Content;

use App\Admin\ContentResource;
use App\Admin\Fields\Field;
use App\Contracts\Proposable;
use App\Models\ContentProposal;
use App\Models\Document;
use App\Models\ExternalResource;
use Illuminate\Database\Eloquent\Model;

/**
 * Human-readable comparison of a proposal for reviewers: field label, value
 * in the base version, proposed value and – on conflict – the current live
 * value. Values are plain text (escaped by Blade).
 */
class ProposalDiff
{
    public function __construct(private readonly ProposalService $proposals, private readonly RevisionService $revisions) {}

    /**
     * @param  ContentResource<Model>  $resource
     * @return list<array{label: string, before: string, after: string, live: string|null}>
     */
    public function rows(ContentProposal $proposal, ContentResource $resource, Model&Proposable $record): array
    {
        $base = $proposal->base_snapshot;
        $payload = $proposal->payload;
        $live = $this->revisions->snapshot($record);
        $conflicts = $this->proposals->conflicts($proposal);
        $changed = $this->proposals->changedParts($base, $payload);

        $fields = [];
        foreach ($resource->formFields($record) as $field) {
            $fields[$field->name] = $field;
        }

        $rows = [];
        foreach ([...$changed['attributes'], ...$changed['relations'], ...$changed['collections']] as $name) {
            $rows[] = [
                'label' => isset($fields[$name]) ? $fields[$name]->label : $this->placementLabel($name),
                'before' => $this->display($name, $fields, $record, $base),
                'after' => $this->display($name, $fields, $record, $payload),
                'live' => array_key_exists($name, $conflicts) ? $this->display($name, $fields, $record, $live) : null,
            ];
        }

        return $rows;
    }

    /**
     * @param  array<string, Field>  $fields
     * @param  array<string, mixed>  $snapshot
     */
    private function display(string $name, array $fields, Model $record, array $snapshot): string
    {
        if (isset($fields[$name])) {
            $value = $fields[$name]->snapshotDisplay($this->preview($record, $snapshot), $snapshot);

            return $value === '' ? '–' : $value;
        }

        $rows = (array) ($snapshot['relations'][$name] ?? []);
        if ($rows === []) {
            return '–';
        }

        $model = $name === 'documents' ? Document::class : ExternalResource::class;
        $titles = $model::withTrashed()->whereKey(array_column($rows, 'id'))->pluck('title', 'id');
        $method = $name === 'documents' ? 'documentSlots' : 'resourceSlots';
        /** @var array<string, string> $slots */
        $slots = method_exists($record, $method) ? (new \ReflectionMethod($record, $method))->invoke(null) : [];

        return implode("\n", array_map(fn (array $row) => ($titles[$row['id']] ?? '#'.$row['id'])
            .' ('.($slots[$row['slot']] ?? $row['slot']).($row['group_label'] ? ' › '.$row['group_label'] : '').', Position '.$row['sort_order'].')', $rows));
    }

    /**
     * Unsaved model instance carrying the snapshot's attributes.
     *
     * @param  array<string, mixed>  $snapshot
     */
    public function preview(Model $record, array $snapshot): Model
    {
        return $record->newFromBuilder(array_merge($record->getAttributes(), (array) ($snapshot['attributes'] ?? [])));
    }

    private function placementLabel(string $name): string
    {
        return match ($name) {
            'documents' => 'Zugeordnete Dokumente',
            'externalResources' => 'Zugeordnete Links',
            default => $name,
        };
    }
}
