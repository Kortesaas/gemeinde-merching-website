<?php

namespace App\Admin\Fields;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Select of a related record (foreign-key column). Only the offered options
 * validate, so constraints (e.g. category context) are enforced server-side.
 */
class BelongsTo extends Field
{
    /** @var (Closure(?Model): array<int, string>)|null */
    protected ?Closure $optionsResolver = null;

    /** @var array<int, string>|null */
    private ?array $resolved = null;

    /**
     * @param  Closure(?Model): array<int, string>  $resolver  id => label
     */
    public function options(Closure $resolver): static
    {
        $this->optionsResolver = $resolver;

        return $this;
    }

    /**
     * @return array<int, string>
     */
    public function optionsFor(?Model $model): array
    {
        return $this->resolved ??= $this->optionsResolver ? ($this->optionsResolver)($model) : [];
    }

    protected function baseRules(?Model $model): array
    {
        return ['integer', Rule::in(array_keys($this->optionsFor($model)))];
    }

    protected function toAttribute(mixed $value): mixed
    {
        return $value === '' || $value === null ? null : (int) $value;
    }

    public function view(): string
    {
        return 'admin.fields.belongs-to';
    }

    public function display(Model $model): string
    {
        $value = $model->getAttribute($this->name);

        return $value === null ? '' : ($this->optionsFor($model)[$value] ?? '#'.$value);
    }
}
