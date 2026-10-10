<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** Exact public source identity; access is checked on every request. */
class LegacyUrl extends Model
{
    protected $guarded = ['id'];

    /** @return MorphTo<Model, $this> */
    public function target(): MorphTo
    {
        return $this->morphTo();
    }
}
