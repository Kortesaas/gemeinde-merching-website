<?php

namespace App\Policies;

use App\Support\Authorization\ContentType;

class SiteAlertPolicy extends ContentPolicy
{
    protected function type(): ContentType
    {
        return ContentType::SiteAlert;
    }
}
