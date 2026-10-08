<?php

namespace App\Admin\Fields;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class Select extends Field
{
    /** @var array<string|int, string> */
    public array $options = [];

    /**
     * @param  array<string|int, string>  $options  value => label
     */
    public function options(array $options): static
    {
        $this->options = $options;

        return $this;
    }

    /**
     * @param  class-string<\BackedEnum>  $enum  enum with a label() method
     */
    public function enum(string $enum): static
    {
        $options = [];
        foreach ($enum::cases() as $case) {
            $options[$case->value] = method_exists($case, 'label') ? $case->label() : $case->name;
        }

        return $this->options($options);
    }

    protected function baseRules(?Model $model): array
    {
        return [Rule::in(array_map('strval', array_keys($this->options)))];
    }

    public function view(): string
    {
        return 'admin.fields.select';
    }

    public function display(Model $model): string
    {
        return $this->options[$this->formValue($model)] ?? '';
    }
}
