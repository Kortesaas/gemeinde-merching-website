<?php

namespace App\Policies;

use App\Support\Authorization\ContentType;

class PersonPolicy extends ContentPolicy
{
    protected function type(): ContentType
    {
        return ContentType::Person;
    }
}
