<?php

namespace App\Policies;

use App\Support\Authorization\ContentType;

class ExternalResourcePolicy extends ContentPolicy
{
    protected function type(): ContentType
    {
        return ContentType::ExternalResource;
    }
}
