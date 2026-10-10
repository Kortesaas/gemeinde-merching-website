<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * Immutable upload metadata; only the secure package workflow creates records.
 *
 * @property CarbonImmutable $created_at
 */
class BudgetSource extends Model
{
    protected $guarded = ['*'];

    protected static function booted(): void
    {
        static::updating(function (self $source) {
            if (array_diff(array_keys($source->getDirty()), ['page_count', 'updated_at']) !== []) {
                throw new \LogicException('Original budget upload metadata is immutable.');
            }
        });
    }

    protected function casts(): array
    {
        return ['size_bytes' => 'integer', 'page_count' => 'integer'];
    }

    public function displayTitle(): string
    {
        return $this->original_filename;
    }
}
