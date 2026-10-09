<?php

namespace App\Models;

use App\Services\Content\RowDefinitions;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $council_member_id
 * @property string $role
 * @property string|null $grouping
 * @property int $sort_order
 */
#[Fillable(['council_term_id', 'council_member_id', 'role', 'grouping', 'sort_order'])]
class CouncilMembership extends Model
{
    public const COLUMNS = ['council_member_id', 'role', 'grouping', 'sort_order'];

    protected $table = 'council_memberships';

    protected static function booted(): void
    {
        static::saving(fn (self $row) => app(RowDefinitions::class)->validate('memberships', $row->getAttributes()));
    }

    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }

    /** @return BelongsTo<CouncilMember, $this> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(CouncilMember::class, 'council_member_id');
    }
}
