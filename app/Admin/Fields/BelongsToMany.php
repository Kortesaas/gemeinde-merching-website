<?php

namespace App\Admin\Fields;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Checkbox group for a many-to-many relation. With sortable(), editors set
 * an order number per selected entry (pivot sort_order).
 */
class BelongsToMany extends Field
{
    public bool $sortable = false;

    /** @var (Closure(?Model): array<int, string>)|null */
    protected ?Closure $optionsResolver = null;

    /** @var array<int, string>|null */
    private ?array $resolved = null;

    /**
     * @param  Closure(?Model): array<int, string>  $resolver
     */
    public function options(Closure $resolver): static
    {
        $this->optionsResolver = $resolver;

        return $this;
    }

    public function sortable(): static
    {
        $this->sortable = true;

        return $this;
    }

    /**
     * @return array<int, string>
     */
    public function optionsFor(?Model $model): array
    {
        return $this->resolved ??= $this->optionsResolver ? ($this->optionsResolver)($model) : [];
    }

    public function validationRules(?Model $model): array
    {
        return [
            // Hidden marker so that unchecking every box clears the relation.
            $this->name.'__present' => ['nullable'],
            $this->name => ['nullable', 'array', 'max:100'],
            $this->name.'.*' => ['integer', 'distinct', Rule::in(array_keys($this->optionsFor($model)))],
            $this->name.'_order' => ['nullable', 'array'],
            $this->name.'_order.*' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ];
    }

    protected function baseRules(?Model $model): array
    {
        return [];
    }

    public function fill(Model $model, array $data): void {}

    public function afterSave(Model $model, array $data): void
    {
        if (! array_key_exists($this->name.'__present', $data) && ! array_key_exists($this->name, $data)) {
            return;
        }

        $ids = array_map('intval', (array) ($data[$this->name] ?? []));
        $orders = (array) ($data[$this->name.'_order'] ?? []);
        $sync = [];
        foreach (array_values($ids) as $position => $id) {
            $order = isset($orders[$id]) && $orders[$id] !== '' ? (int) $orders[$id] : $position * 10;
            $sync[$id] = $this->sortable ? ['sort_order' => $order] : [];
        }

        $model->{$this->name}()->sync($sync);
    }

    public function formValue(Model $model): mixed
    {
        return $model->exists ? $model->{$this->name}()->pluck($model->{$this->name}()->getRelated()->getQualifiedKeyName())->map(fn ($id) => (int) $id)->all() : [];
    }

    public function snapshotValue(Model $preview, array $snapshot): mixed
    {
        return array_map(fn (array $row) => (int) $row['id'], (array) ($snapshot['relations'][$this->name] ?? []));
    }

    public function snapshotDisplay(Model $preview, array $snapshot): string
    {
        $options = $this->optionsFor($preview);
        $rows = (array) ($snapshot['relations'][$this->name] ?? []);
        usort($rows, fn (array $a, array $b) => ($a['sort_order'] ?? 0) <=> ($b['sort_order'] ?? 0));

        return implode(', ', array_map(fn (array $row) => $options[(int) $row['id']] ?? '#'.$row['id'], $rows));
    }

    /**
     * Sort order per related id taken from a snapshot.
     *
     * @param  array<string, mixed>  $snapshot
     * @return array<int, int>
     */
    public function snapshotOrders(array $snapshot): array
    {
        $orders = [];
        foreach ((array) ($snapshot['relations'][$this->name] ?? []) as $row) {
            $orders[(int) $row['id']] = (int) ($row['sort_order'] ?? 0);
        }

        return $orders;
    }

    /**
     * Current sort order per related id (sortable relations).
     *
     * @return array<int, int>
     */
    public function orderValues(Model $model): array
    {
        if (! $model->exists) {
            return [];
        }

        return $model->{$this->name}()->get()->mapWithKeys(fn (Model $related) => [
            (int) $related->getKey() => (int) $related->getRelation('pivot')->getAttribute('sort_order'),
        ])->all();
    }

    public function view(): string
    {
        return 'admin.fields.belongs-to-many';
    }

    public function display(Model $model): string
    {
        $options = $this->optionsFor($model);

        return implode(', ', array_map(fn ($id) => $options[$id] ?? '#'.$id, (array) $this->formValue($model)));
    }
}
