<?php

namespace App\Models\Concerns;

use App\Models\ExternalResource;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Managed external links placed on this record in a validated slot.
 */
trait HasResourcePlacements
{
    /**
     * @return BelongsToMany<ExternalResource, $this>
     */
    public function externalResources(): BelongsToMany
    {
        return $this->belongsToMany(ExternalResource::class)
            ->withPivot(['id', 'slot', 'group_label', 'sort_order'])
            ->withTimestamps()
            ->orderByPivot('slot')
            ->orderByPivot('sort_order');
    }

    /**
     * @return array<string, string>
     */
    abstract public static function resourceSlots(): array;
}
