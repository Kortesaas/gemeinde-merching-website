<?php

namespace App\Policies;

use App\Support\Authorization\ContentType;

class TagPolicy extends ContentPolicy
{
    protected function type(): ContentType
    {
        return ContentType::Taxonomy;
    }
}
