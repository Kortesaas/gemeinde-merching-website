<?php

namespace App\Services\Content;

use App\Exceptions\DomainRuleViolation;
use App\Models;
use App\Rules\ControlledText;
use App\Support\Content\ControlledTable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/** Shared schemas for accessible row forms and model/revision persistence. */
final class RowDefinitions
{
    public const BLOCK_TYPES = ['table' => 'Tabelle (Kopfzeile, Tabulatoren zwischen Zellen)', 'text' => 'Text', 'heading' => 'Überschrift', 'image' => 'Bild', 'gallery' => 'Galerie', 'callout' => 'Hinweis', 'contact' => 'Ansprechperson', 'department' => 'Zuständige Stelle', 'downloads' => 'Dokument / Download', 'services' => 'Verwandte Leistung', 'events' => 'Veranstaltung', 'accordion' => 'Aufklappbare Information', 'external' => 'Externer Dienst / Link', 'location' => 'Ort / Karteninformation'];

    /** @return array<string, array{label:string,rules:list<mixed>,options?:array<int|string,string>,multiline?:bool,type?:string}> */
    public function fields(string $definition, bool $withOptions = true): array
    {
        $text = fn (string $label, int $max = 255, bool $required = false) => ['label' => $label, 'rules' => [$required ? 'required' : 'nullable', 'string', 'max:'.$max, new ControlledText]];
        $reference = function (string $label, string $table, string $class, bool $required = false) use ($withOptions): array {
            $titles = [];
            foreach ($withOptions ? $class::query()->orderBy('id')->get() : [] as $record) {
                $titles[$record->getKey()] = $record->displayTitle();
            }

            return ['label' => $label, 'rules' => [$required ? 'required' : 'nullable', 'integer', Rule::exists($table, 'id')->whereNull('deleted_at')], 'options' => $titles];
        };
        $order = ['sort_order' => ['label' => 'Position', 'rules' => ['required', 'integer', 'min:0', 'max:65535'], 'type' => 'number']];

        return match ($definition) {
            'budgetComponents' => $order + ['budget_source_id' => ['label' => 'PDF-Datei', 'rules' => ['required', 'integer', Rule::exists('budget_sources', 'id')]]],
            'fees' => $order + ['description' => $text('Beschreibung', required: true), 'context' => $text('Gültigkeit / Kontext'), 'amount' => ['label' => 'Betrag in EUR (optional)', 'rules' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999.99'], 'type' => 'number'], 'note' => $text('Hinweis')],
            'items' => $order + ['media_id' => $reference('Bild aus der Medienbibliothek', 'media', Models\Media::class, true), 'caption' => $text('Abweichende Bildunterschrift', 5000) + ['multiline' => true], 'alt_override' => $text('Alternativtext für diesen Kontext', 2000), 'alt_context' => $text('Begründung des abweichenden Bildkontexts')],
            'memberships' => $order + ['council_member_id' => $reference('Ratsmitglied', 'council_members', Models\CouncilMember::class, true), 'role' => $text('Rolle in der Wahlperiode', required: true), 'grouping' => $text('Liste / Gruppierung / Partei (optional)')],
            'committeeMemberships' => $order + ['council_member_id' => $reference('Ratsmitglied', 'council_members', Models\CouncilMember::class, true), 'role' => $text('Rolle im Ausschuss', required: true)],
            'blocks' => $order + [
                'type' => ['label' => 'Baustein', 'rules' => ['required', Rule::in(array_keys(self::BLOCK_TYPES))], 'options' => self::BLOCK_TYPES],
                'heading' => $text('Überschrift / Aufklapptitel'),
                'heading_level' => ['label' => 'Überschriftenebene (nur Überschrift)', 'rules' => ['nullable', 'integer', 'in:2,3,4'], 'options' => [2 => 'H2 – Abschnitt', 3 => 'H3 – Unterabschnitt', 4 => 'H4 – Unterpunkt']],
                'text' => $text('Text (sicheres Markdown)', 20000) + ['multiline' => true],
                'media_id' => $reference('Bild', 'media', Models\Media::class),
                'gallery_id' => $reference('Galerie', 'galleries', Models\Gallery::class),
                'document_id' => $reference('Dokument', 'documents', Models\Document::class),
                'person_id' => $reference('Ansprechperson', 'people', Models\Person::class),
                'department_id' => $reference('Stelle', 'departments', Models\Department::class),
                'service_id' => $reference('Leistung', 'services', Models\Service::class),
                'event_id' => $reference('Veranstaltung', 'events', Models\Event::class),
                'location_id' => $reference('Ort', 'locations', Models\Location::class),
                'external_resource_id' => $reference('Externer Dienst / Link', 'external_resources', Models\ExternalResource::class),
            ],
            default => throw new \LogicException('Unknown row definition.'),
        };
    }

    /** @param array<string,mixed> $row */
    public function validate(string $definition, array $row): void
    {
        $rules = array_map(fn ($field) => $field['rules'], $this->fields($definition, false));
        $validator = Validator::make($row, $rules);
        if ($validator->fails()) {
            throw new DomainRuleViolation($validator->errors()->first(), $definition);
        }
        if ($definition === 'blocks') {
            $type = $row['type'];
            $required = ['image' => 'media_id', 'gallery' => 'gallery_id', 'downloads' => 'document_id', 'contact' => 'person_id', 'department' => 'department_id', 'services' => 'service_id', 'events' => 'event_id', 'location' => 'location_id', 'external' => 'external_resource_id'][$type] ?? null;
            $references = array_keys(array_filter(Arr::only($row, array_keys(Models\ContentBlock::REFERENCES)), fn ($value) => $value !== null && $value !== ''));
            if ($references !== ($required ? [$required] : [])) {
                throw new DomainRuleViolation('Bitte genau den zum Baustein passenden Eintrag zuordnen.', 'blocks');
            }
            if ((in_array($type, ['heading', 'accordion'], true) && empty(trim((string) ($row['heading'] ?? ''))))
                || ($type === 'heading' && empty($row['heading_level']))
                || (in_array($type, ['text', 'callout', 'accordion'], true) && empty(trim((string) ($row['text'] ?? ''))))) {
                throw new DomainRuleViolation('Überschrift, Ebene oder Text des Bausteins fehlt.', 'blocks');
            }
            if ($type === 'table') {
                try {
                    ControlledTable::rows((string) ($row['text'] ?? ''));
                } catch (\InvalidArgumentException $e) {
                    throw new DomainRuleViolation($e->getMessage(), 'blocks');
                }
                if (empty(trim((string) ($row['heading'] ?? '')))) {
                    throw new DomainRuleViolation('Die Tabelle benötigt einen beschreibenden Titel.', 'blocks');
                }
            }
            if ($type === 'image' && ! Models\Media::query()->findOrFail((int) $row['media_id'])->isImage()) {
                throw new DomainRuleViolation('Ein Bildbaustein benötigt eine Bilddatei.', 'blocks');
            }
            if ($type !== 'heading' && ! empty($row['heading_level'])) {
                throw new DomainRuleViolation('Eine Überschriftenebene gehört nur zum Baustein Überschrift.', 'blocks');
            }
            if (preg_match('/^\s*#{1,6}\s|^\s*(?:={2,}|-{2,})\s*$/mu', (string) ($row['text'] ?? ''))) {
                throw new DomainRuleViolation('Bitte Überschriften als eigenen Baustein mit gewählter Ebene anlegen.', 'blocks');
            }
        }
        if ($definition === 'items') {
            $media = Models\Media::query()->findOrFail((int) $row['media_id']);
            if (! $media->isImage()) {
                throw new DomainRuleViolation('Galerien verwenden ausschließlich Bilder.', 'items');
            }
            if (! empty($row['alt_override']) && (empty(trim((string) ($row['alt_context'] ?? ''))) || $media->is_decorative)) {
                throw new DomainRuleViolation('Abweichender Alternativtext benötigt einen begründeten Kontext und ein bedeutungstragendes Bild.', 'items');
            }
        }
    }
}
