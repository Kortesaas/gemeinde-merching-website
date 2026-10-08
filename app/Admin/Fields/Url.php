<?php

namespace App\Admin\Fields;

use App\Rules\SafeUrl;
use Illuminate\Database\Eloquent\Model;

/**
 * Absolute http(s) link, validated against unsafe schemes (javascript: …).
 */
class Url extends Text
{
    public string $inputType = 'url';

    public int $max = 2048;

    protected function baseRules(?Model $model): array
    {
        return ['string', 'max:2048', new SafeUrl];
    }
}
