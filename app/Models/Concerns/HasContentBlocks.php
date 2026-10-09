<?php

namespace App\Models\Concerns;

use App\Models\ContentBlock;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasContentBlocks
{
    /** @return MorphMany<ContentBlock, $this> */
    public function blocks(): MorphMany
    {
        return $this->morphMany(ContentBlock::class, 'owner')->orderBy('sort_order')->orderBy('id');
    }

    public static function bootHasContentBlocks(): void
    {
        static::forceDeleted(fn ($owner) => $owner->blocks()->delete());
    }
}
