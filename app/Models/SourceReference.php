<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Where a record was imported from (e.g. source_system "wordpress", source_id
 * "4711", original_url). Used by the later content migration.
 *
 * @property int $id
 * @property string $source_system
 * @property string $source_id
 * @property string|null $original_url
 * @property CarbonImmutable|null $imported_at
 */
#[Fillable(['source_system', 'source_id', 'original_url', 'imported_at'])]
class SourceReference extends Model
{
    protected function casts(): array
    {
        return ['imported_at' => 'immutable_datetime'];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function referenceable(): MorphTo
    {
        return $this->morphTo();
    }
}
