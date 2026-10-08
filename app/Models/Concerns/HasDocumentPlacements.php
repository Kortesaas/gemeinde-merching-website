<?php

namespace App\Models\Concerns;

use App\Models\Document;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Documents placed on this record in a validated slot (see documentSlots()),
 * with an optional group heading (e.g. "2026") and a sort order.
 * Explicit pivot table per owner type (e.g. document_page) with foreign keys.
 */
trait HasDocumentPlacements
{
    /**
     * @return BelongsToMany<Document, $this>
     */
    public function documents(): BelongsToMany
    {
        return $this->belongsToMany(Document::class)
            ->withPivot(['id', 'slot', 'group_label', 'sort_order'])
            ->withTimestamps()
            ->orderByPivot('slot')
            ->orderByPivot('sort_order');
    }

    /**
     * Allowed slots: identifier => label.
     *
     * @return array<string, string>
     */
    abstract public static function documentSlots(): array;
}
