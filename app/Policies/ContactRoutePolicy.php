<?php

namespace App\Policies;

use App\Support\Authorization\ContentType;

class ContactRoutePolicy extends ContentPolicy
{
    protected function type(): ContentType
    {
        return ContentType::ContactRoute;
    }
}
