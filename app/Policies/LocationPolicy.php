<?php

namespace App\Policies;

use App\Support\Authorization\ContentType;

class LocationPolicy extends ContentPolicy
{
    protected function type(): ContentType
    {
        return ContentType::Location;
    }
}
