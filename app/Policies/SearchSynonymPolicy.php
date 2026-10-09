<?php

namespace App\Policies;

use App\Support\Authorization\ContentType;

class SearchSynonymPolicy extends ContentPolicy
{
    protected function type(): ContentType
    {
        return ContentType::SearchSynonym;
    }
}
