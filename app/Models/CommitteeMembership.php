<?php

namespace App\Models;

use App\Services\Content\RowDefinitions;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $council_member_id
 * @property string $role
 * @property int $sort_order
 */
#[Fillable(['committee_id', 'council_member_id', 'role', 'sort_order'])]
class CommitteeMembership extends Model
{
    public const COLUMNS = ['council_member_id', 'role', 'sort_order'];

    protected $table = 'committee_memberships';

    protected static function booted(): void
    {
        static::saving(fn (self $row) => app(RowDefinitions::class)->validate('committeeMemberships', $row->getAttributes()));
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
