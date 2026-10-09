<?php

namespace App\Support\Content;

use App\Models\NavigationItem;
use Illuminate\Database\Eloquent\Model;

/** One visible menu entry, with its target resolved once when the tree is built. */
final readonly class NavigationNode
{
    /** @param list<NavigationNode> $children */
    public function __construct(public NavigationItem $item, public array $children, public ?string $href = null, public ?Model $target = null) {}

    public function isExternal(): bool
    {
        return $this->href !== null && ! str_starts_with($this->href, '/');
    }
}
