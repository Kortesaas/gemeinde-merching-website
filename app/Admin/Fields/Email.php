<?php

namespace App\Admin\Fields;

use Illuminate\Database\Eloquent\Model;

class Email extends Text
{
    public string $inputType = 'email';

    protected function baseRules(?Model $model): array
    {
        return ['string', 'max:255', 'email:rfc'];
    }
}
