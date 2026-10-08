<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

/**
 * Employee account for the backend.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $password
 * @property bool $is_active
 * @property string|null $two_factor_secret
 * @property CarbonImmutable|null $two_factor_confirmed_at
 * @property int|null $two_factor_last_used_timestep
 * @property CarbonImmutable|null $password_changed_at
 * @property CarbonImmutable|null $last_login_at
 */
#[Fillable(['name', 'email', 'password', 'is_active'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_last_used_timestep'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * "Remember me" logins are intentionally not supported.
     *
     * @var string
     */
    protected $rememberTokenName = '';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'two_factor_secret' => 'encrypted',
            'two_factor_confirmed_at' => 'immutable_datetime',
            'two_factor_last_used_timestep' => 'integer',
            'password_changed_at' => 'immutable_datetime',
            'last_login_at' => 'immutable_datetime',
        ];
    }

    /**
     * Normalise e-mail addresses so logins are case-insensitive.
     *
     * @return Attribute<string, string>
     */
    protected function email(): Attribute
    {
        return Attribute::make(set: fn (string $value) => mb_strtolower(trim($value)));
    }

    public function hasEnabledTwoFactor(): bool
    {
        return $this->two_factor_secret !== null && $this->two_factor_confirmed_at !== null;
    }

    /**
     * @return HasMany<TwoFactorRecoveryCode, $this>
     */
    public function recoveryCodes(): HasMany
    {
        return $this->hasMany(TwoFactorRecoveryCode::class);
    }
}
