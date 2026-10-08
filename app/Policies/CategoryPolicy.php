<?php

namespace App\Policies;

use App\Support\Authorization\ContentType;

class CategoryPolicy extends ContentPolicy
{
    protected function type(): ContentType
    {
        return ContentType::Taxonomy;
    }
}
