<?php

namespace App\Admin\Fields;

use Illuminate\Database\Eloquent\Model;

class Checkbox extends Field
{
    protected function baseRules(?Model $model): array
    {
        return ['boolean'];
    }

    public function validationRules(?Model $model): array
    {
        return [$this->name => ['nullable', 'boolean']];
    }

    public function fill(Model $model, array $data): void
    {
        $model->setAttribute($this->name, (bool) ($data[$this->name] ?? false));
    }

    public function view(): string
    {
        return 'admin.fields.checkbox';
    }

    public function display(Model $model): string
    {
        return $model->getAttribute($this->name) ? 'ja' : 'nein';
    }
}
