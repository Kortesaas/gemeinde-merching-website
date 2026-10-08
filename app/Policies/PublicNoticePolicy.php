<?php

namespace App\Policies;

use App\Support\Authorization\ContentType;

class PublicNoticePolicy extends ContentPolicy
{
    protected function type(): ContentType
    {
        return ContentType::PublicNotice;
    }
}
