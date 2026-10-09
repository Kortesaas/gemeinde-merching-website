<?php

namespace App\Contracts;

use App\Support\Content\QualityIssue;
use Illuminate\Database\Eloquent\Model;

interface QualityCheck
{
    public function supports(Model $model): bool;

    /** @return list<QualityIssue> */
    public function inspect(Model $model): array;
}
