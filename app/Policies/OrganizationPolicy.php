<?php

namespace App\Policies;

use App\Support\Authorization\ContentType;

class OrganizationPolicy extends ContentPolicy
{
    protected function type(): ContentType
    {
        return ContentType::Organization;
    }
}
