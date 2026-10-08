<?php

namespace App\Models\Concerns;

use App\Models\SourceReference;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Optional provenance for migrated content (e.g. WordPress post ID and URL).
 * Only for models with SoftDeletes: references are removed on force delete.
 */
trait HasSourceReferences
{
    public static function bootHasSourceReferences(): void
    {
        static::forceDeleted(function (self $model) {
            $model->sourceReferences()->delete();
        });
    }

    /**
     * @return MorphMany<SourceReference, $this>
     */
    public function sourceReferences(): MorphMany
    {
        return $this->morphMany(SourceReference::class, 'referenceable');
    }
}
