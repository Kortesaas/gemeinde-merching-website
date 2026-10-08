<?php

namespace App\Policies;

use App\Support\Authorization\ContentType;

class DocumentPolicy extends ContentPolicy
{
    protected function type(): ContentType
    {
        return ContentType::Document;
    }
}
