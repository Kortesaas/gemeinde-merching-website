<?php

namespace App\Policies;

use App\Support\Authorization\ContentType;

class ArticlePolicy extends ContentPolicy
{
    protected function type(): ContentType
    {
        return ContentType::Article;
    }
}
