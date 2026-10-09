<?php

namespace App\Policies;

use App\Support\Authorization\ContentType;

class CouncilMemberPolicy extends ContentPolicy
{
    protected function type(): ContentType
    {
        return ContentType::CouncilMember;
    }
}
