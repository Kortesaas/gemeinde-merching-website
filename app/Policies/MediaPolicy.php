<?php

namespace App\Policies;

use App\Support\Authorization\ContentType;

class MediaPolicy extends ContentPolicy
{
    protected function type(): ContentType
    {
        return ContentType::Media;
    }
}
