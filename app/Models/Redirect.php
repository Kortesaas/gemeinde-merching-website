<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Path redirect (legacy URLs). Written through App\Services\Routing\RedirectManager,
 * which prevents duplicates, loops, chains and collisions with content routes.
 *
 * @property int $id
 * @property string $source_path
 * @property string $source_key
 * @property string|null $destination
 * @property int $status_code
 * @property bool $is_active
 * @property string|null $notes
 */
class Redirect extends Model
{
    public const STATUS_CODES = [301 => '301 – dauerhaft verschoben', 302 => '302 – vorübergehend', 410 => '410 – entfernt'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['status_code' => 'integer', 'is_active' => 'boolean'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function displayTitle(): string
    {
        return $this->source_path;
    }
}
