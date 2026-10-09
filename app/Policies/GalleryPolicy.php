<?php

namespace App\Policies;

use App\Support\Authorization\ContentType;

class GalleryPolicy extends ContentPolicy
{
    protected function type(): ContentType
    {
        return ContentType::Gallery;
    }
}
