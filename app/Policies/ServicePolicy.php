<?php

namespace App\Policies;

use App\Support\Authorization\ContentType;

class ServicePolicy extends ContentPolicy
{
    protected function type(): ContentType
    {
        return ContentType::Service;
    }
}
