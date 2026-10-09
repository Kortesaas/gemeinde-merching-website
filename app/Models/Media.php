<?php

namespace App\Models;

use App\Contracts\Proposable;
use App\Models\Concerns\HasProposals;
use App\Models\Concerns\HasPublication;
use App\Models\Concerns\HasRevisions;
use App\Models\Concerns\TracksEditors;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $title
 * @property string $file_path
 * @property string $original_filename
 * @property string $mime_type
 * @property string $extension
 * @property int $size_bytes
 * @property int|null $width
 * @property int|null $height
 * @property string|null $alt_text
 * @property bool $is_decorative
 */
#[Fillable(['title', 'alt_text', 'is_decorative', 'caption', 'copyright', 'creator', 'language'])]
class Media extends Model implements Proposable
{
    use HasProposals, HasPublication, HasRevisions, SoftDeletes, TracksEditors;

    protected $table = 'media';

    protected $attributes = ['language' => 'de', 'is_decorative' => false];

    protected function casts(): array
    {
        return ['is_decorative' => 'boolean', 'size_bytes' => 'integer', 'width' => 'integer', 'height' => 'integer'];
    }

    public function displayTitle(): string
    {
        return $this->title;
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    public function hasAccessibleAlternative(): bool
    {
        return $this->is_decorative || ($this->alt_text !== null && trim($this->alt_text) !== '');
    }

    /** @return list<string> */
    public function revisionAttributes(): array
    {
        return ['title', 'alt_text', 'is_decorative', 'caption', 'copyright', 'creator', 'language'];
    }
}
