<?php

namespace App\Admin\Fields;

use Illuminate\Database\Eloquent\Model;

/**
 * A form field of an admin resource: rendering, validation and how the value
 * is written to the model. Views live in resources/views/admin/fields.
 */
abstract class Field
{
    public bool $required = false;

    public ?string $hint = null;

    /** @var list<mixed> */
    protected array $extraRules = [];

    final public function __construct(public readonly string $name, public readonly string $label) {}

    public static function make(string $name, string $label): static
    {
        return new static($name, $label);
    }

    public function required(bool $required = true): static
    {
        $this->required = $required;

        return $this;
    }

    public function hint(string $hint): static
    {
        $this->hint = $hint;

        return $this;
    }

    /**
     * @param  list<mixed>  $rules
     */
    public function rules(array $rules): static
    {
        $this->extraRules = [...$this->extraRules, ...$rules];

        return $this;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function validationRules(?Model $model): array
    {
        return [$this->name => [$this->required ? 'required' : 'nullable', ...$this->baseRules($model), ...$this->extraRules]];
    }

    /**
     * @return list<mixed>
     */
    abstract protected function baseRules(?Model $model): array;

    abstract public function view(): string;

    /**
     * Write the validated value to the model (before save).
     *
     * @param  array<string, mixed>  $data
     */
    public function fill(Model $model, array $data): void
    {
        if (array_key_exists($this->name, $data)) {
            $model->setAttribute($this->name, $this->toAttribute($data[$this->name]));
        }
    }

    /**
     * Persist relations (after save).
     *
     * @param  array<string, mixed>  $data
     */
    public function afterSave(Model $model, array $data): void {}

    protected function toAttribute(mixed $value): mixed
    {
        return $value === '' ? null : $value;
    }

    /**
     * Value shown in the form (old input wins).
     */
    public function formValue(Model $model): mixed
    {
        $value = $model->getAttribute($this->name);

        return $value instanceof \BackedEnum ? $value->value : $value;
    }

    /**
     * Human-readable value for lists and revision views.
     */
    public function display(Model $model): string
    {
        $value = $this->formValue($model);

        return is_scalar($value) ? (string) $value : '';
    }
}
