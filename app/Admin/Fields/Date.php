<?php

namespace App\Admin\Fields;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * Calendar date without time (e.g. valid_from); no time-zone conversion.
 */
class Date extends Field
{
    protected function baseRules(?Model $model): array
    {
        return ['date_format:Y-m-d'];
    }

    public function view(): string
    {
        return 'admin.fields.date';
    }

    public function formValue(Model $model): mixed
    {
        $value = $model->getAttribute($this->name);

        return $value instanceof DateTimeInterface ? $value->format('Y-m-d') : $value;
    }

    public function display(Model $model): string
    {
        $value = $model->getAttribute($this->name);

        return $value instanceof DateTimeInterface ? $value->format('d.m.Y') : '';
    }
}
