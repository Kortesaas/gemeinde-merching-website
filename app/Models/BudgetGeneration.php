<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * Stored output and ordered manifest; never mutated by editorial revisions.
 *
 * @property list<array{id:int,position:int,original_filename:string,size_bytes:int,sha256:string,page_count:int,uploaded_at:string}> $sources
 * @property CarbonImmutable $created_at
 */
class BudgetGeneration extends Model
{
    protected $guarded = ['*'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Stored budget generations and publication records are immutable.'));
    }

    protected function casts(): array
    {
        return ['sources' => 'array', 'size_bytes' => 'integer', 'page_count' => 'integer'];
    }
}
