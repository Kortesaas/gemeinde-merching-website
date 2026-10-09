<?php

namespace App\Policies;

use App\Support\Authorization\ContentType;

class CommitteePolicy extends ContentPolicy
{
    protected function type(): ContentType
    {
        return ContentType::Committee;
    }
}
