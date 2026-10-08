<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Hashed single-use MFA recovery code.
 *
 * @property int $id
 * @property int $user_id
 * @property string $code_hash
 * @property CarbonImmutable|null $used_at
 */
#[Fillable(['code_hash', 'used_at'])]
class TwoFactorRecoveryCode extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'used_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
