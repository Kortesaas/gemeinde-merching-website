<?php

namespace App\Admin\Fields;

use Illuminate\Database\Eloquent\Model;

class Textarea extends Field
{
    public int $max = 10000;

    public int $rows = 4;

    protected function baseRules(?Model $model): array
    {
        return ['string', 'max:'.$this->max];
    }

    public function view(): string
    {
        return 'admin.fields.textarea';
    }
}
