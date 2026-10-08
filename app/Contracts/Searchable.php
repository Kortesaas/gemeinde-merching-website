<?php

namespace App\Contracts;

use App\Support\Search\SearchDocument;

/**
 * Exposes the text of a record for the future MySQL-based site search.
 */
interface Searchable
{
    public function toSearchDocument(): SearchDocument;
}
