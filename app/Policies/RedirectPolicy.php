<?php

namespace App\Policies;

use App\Support\Authorization\ContentType;

class RedirectPolicy extends ContentPolicy
{
    protected function type(): ContentType
    {
        return ContentType::Redirect;
    }
}
