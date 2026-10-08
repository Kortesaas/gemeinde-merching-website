<?php

namespace App\Policies;

use App\Support\Authorization\ContentType;

class NavigationItemPolicy extends ContentPolicy
{
    protected function type(): ContentType
    {
        return ContentType::Navigation;
    }
}
