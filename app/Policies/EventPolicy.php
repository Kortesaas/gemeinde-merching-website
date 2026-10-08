<?php

namespace App\Policies;

use App\Support\Authorization\ContentType;

class EventPolicy extends ContentPolicy
{
    protected function type(): ContentType
    {
        return ContentType::Event;
    }
}
