<?php

namespace App\Models;

use App\Services\Content\RowDefinitions;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $media_id
 * @property string|null $caption
 * @property string|null $alt_override
 * @property string|null $alt_context
 * @property int $sort_order
 */
#[Fillable(['gallery_id', 'media_id', 'caption', 'alt_override', 'alt_context', 'sort_order'])]
class GalleryItem extends Model
{
    public const COLUMNS = ['media_id', 'caption', 'alt_override', 'alt_context', 'sort_order'];

    protected $table = 'gallery_items';

    protected static function booted(): void
    {
        static::saving(fn (self $row) => app(RowDefinitions::class)->validate('items', $row->getAttributes()));
    }

    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }

    /** @return BelongsTo<Media, $this> */
    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    public function alternative(Media $media): string
    {
        return $media->is_decorative ? '' : ($this->alt_override ?? $media->alt_text ?? '');
    }
}
