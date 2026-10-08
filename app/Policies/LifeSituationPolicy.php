<?php

namespace App\Policies;

use App\Support\Authorization\ContentType;

class LifeSituationPolicy extends ContentPolicy
{
    protected function type(): ContentType
    {
        return ContentType::LifeSituation;
    }
}
