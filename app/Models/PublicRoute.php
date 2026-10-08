<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A public URL path that resolves to a content record. Managed exclusively by
 * App\Services\Routing\RouteManager (never mass-assigned from requests).
 *
 * Canonical route = the record's address. Non-canonical routes are former
 * paths of the same record and redirect (301) to the canonical one.
 *
 * @property int $id
 * @property string $path
 * @property string $path_key
 * @property string $routable_type
 * @property int $routable_id
 * @property bool $is_canonical
 * @property string|null $canonical_for
 * @property bool $is_active
 */
class PublicRoute extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_canonical' => 'boolean', 'is_active' => 'boolean'];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function routable(): MorphTo
    {
        return $this->morphTo()->withTrashed();
    }
}
