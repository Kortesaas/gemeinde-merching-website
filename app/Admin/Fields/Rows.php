<?php

namespace App\Admin\Fields;

use App\Exceptions\DomainRuleViolation;
use App\Services\Content\RowDefinitions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Arr;

/** Ordered child rows; keyboard-operable numeric ordering, no JS required. */
class Rows extends Field
{
    public string $rowDefinition;

    public function definition(string $definition): static
    {
        $this->rowDefinition = $definition;

        return $this;
    }

    /** @return array<string,array{label:string,rules:list<mixed>,options?:array<int|string,string>,multiline?:bool,type?:string}> */
    public function columns(): array
    {
        return app(RowDefinitions::class)->fields($this->rowDefinition);
    }

    protected function baseRules(?Model $model): array
    {
        return [];
    }

    public function validationRules(?Model $model): array
    {
        return [$this->name.'__present' => ['nullable'], $this->name => ['nullable', 'array', 'max:100'], $this->name.'.*' => ['array:'.implode(',', [...array_keys($this->columns()), '_remove'])]];
    }

    public function fill(Model $model, array $data): void {}

    public function afterSave(Model $model, array $data): void
    {
        if (! array_key_exists($this->name, $data) && ! array_key_exists($this->name.'__present', $data)) {
            return;
        }
        /** @var HasMany<Model,Model> $query */
        $query = $model->{$this->name}();
        $rows = [];
        foreach ((array) ($data[$this->name] ?? []) as $row) {
            if (! is_array($row)) {
                throw new DomainRuleViolation('Ungültiger Baustein.', $this->name);
            }
            if (! empty($row['_remove'])) {
                continue;
            }
            $values = Arr::only($row, array_keys($this->columns()));
            // Blank add slots do not create content; incomplete filled rows fail validation.
            if (count(array_filter(Arr::except($values, ['sort_order']), fn ($v) => $v !== null && $v !== '')) === 0) {
                continue;
            }
            $values['sort_order'] ??= 0;
            app(RowDefinitions::class)->validate($this->rowDefinition, $values);
            $rows[] = $values;
        }
        foreach (match ($this->rowDefinition) {
            'items' => ['media_id'], 'memberships', 'committeeMemberships' => ['council_member_id'], default => []
        } as $unique) {
            $ids = array_column($rows, $unique);
            if (count($ids) !== count(array_unique($ids))) {
                throw new DomainRuleViolation('Bitte jeden Eintrag nur einmal zuordnen.', $this->name);
            }
        }
        usort($rows, fn ($a, $b) => $a['sort_order'] <=> $b['sort_order']);
        $query->delete();
        foreach ($rows as $position => $row) {
            $row['sort_order'] = $position;
            $query->create($row);
        }
        $model->unsetRelation($this->name);
    }

    public function formValue(Model $model): mixed
    {
        return $model->exists ? $model->{$this->name}()->get()->map(fn ($row) => Arr::only($row->getAttributes(), array_keys($this->columns())))->all() : [];
    }

    public function snapshotValue(Model $preview, array $snapshot): mixed
    {
        return $snapshot['collections'][$this->name] ?? [];
    }

    public function snapshotDisplay(Model $preview, array $snapshot): string
    {
        return implode("\n", array_map(function ($row) {
            $values = [];
            foreach ($this->columns() as $key => $column) {
                if (isset($row[$key]) && $row[$key] !== '') {
                    $values[] = $column['label'].': '.($column['options'][$row[$key]] ?? $row[$key]);
                }
            }

            return implode(' · ', $values);
        }, $this->snapshotValue($preview, $snapshot)));
    }

    public function view(): string
    {
        return 'admin.fields.rows';
    }
}
