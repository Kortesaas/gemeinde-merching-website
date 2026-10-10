<?php

namespace App\Models;

use App\Enums\AccessibilityStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Immutable receipt of a successful publication, including actor name and URL.
 *
 * @property AccessibilityStatus $accessibility_status
 */
class BudgetPublication extends Model
{
    protected $guarded = ['*'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Stored budget generations and publication records are immutable.'));
    }

    protected function casts(): array
    {
        return ['publish_at' => 'immutable_datetime', 'expires_at' => 'immutable_datetime', 'show_components' => 'boolean', 'accessibility_status' => AccessibilityStatus::class];
    }

    /** @return BelongsTo<BudgetGeneration, $this> */
    public function generation(): BelongsTo
    {
        return $this->belongsTo(BudgetGeneration::class, 'budget_generation_id');
    }
}
