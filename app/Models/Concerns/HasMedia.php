<?php

namespace App\Models\Concerns;

use App\Models\Media;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

trait HasMedia
{
    /** @return BelongsToMany<Media, $this> */
    public function media(): BelongsToMany
    {
        return $this->belongsToMany(Media::class, Str::snake(class_basename($this)).'_media')->withPivot('sort_order')->withTimestamps()->orderByPivot('sort_order');
    }
}
