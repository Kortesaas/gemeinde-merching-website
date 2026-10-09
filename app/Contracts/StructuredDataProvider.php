<?php

namespace App\Contracts;

use Illuminate\Database\Eloquent\Model;

interface StructuredDataProvider
{
    /** @return array<string,mixed> Schema.org data derived from verified structured records. */
    public function forModel(?Model $model): array;
}
