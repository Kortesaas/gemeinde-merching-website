<?php

namespace App\Policies;

use App\Support\Authorization\ContentType;

class CouncilTermPolicy extends ContentPolicy
{
    protected function type(): ContentType
    {
        return ContentType::CouncilTerm;
    }
}
