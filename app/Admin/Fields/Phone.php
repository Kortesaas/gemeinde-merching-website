<?php

namespace App\Admin\Fields;

use Illuminate\Database\Eloquent\Model;

class Phone extends Text
{
    public string $inputType = 'tel';

    protected function baseRules(?Model $model): array
    {
        return ['string', 'max:50', 'regex:/^[0-9 +()\/\-]{3,50}$/'];
    }
}
