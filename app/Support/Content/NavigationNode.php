<?php

namespace App\Support\Content;

use App\Models\NavigationItem;

final readonly class NavigationNode
{
    /** @param list<NavigationNode> $children */
    public function __construct(public NavigationItem $item, public array $children) {}
}
