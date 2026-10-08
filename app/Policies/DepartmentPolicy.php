<?php

namespace App\Policies;

use App\Support\Authorization\ContentType;

class DepartmentPolicy extends ContentPolicy
{
    protected function type(): ContentType
    {
        return ContentType::Department;
    }
}
