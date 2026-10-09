<?php

namespace App\Models;

use App\Services\Content\RowDefinitions;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $description
 * @property string|null $context
 * @property string|null $amount
 * @property string|null $note
 * @property int $sort_order
 */
#[Fillable(['service_id', 'description', 'context', 'amount', 'note', 'sort_order'])]
class ServiceFee extends Model
{
    public const COLUMNS = ['description', 'context', 'amount', 'note', 'sort_order'];

    protected $table = 'service_fees';

    protected static function booted(): void
    {
        static::saving(fn (self $row) => app(RowDefinitions::class)->validate('fees', $row->getAttributes()));
    }

    protected function casts(): array
    {
        return ['sort_order' => 'integer', 'amount' => 'decimal:2'];
    }
}
