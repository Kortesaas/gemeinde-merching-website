<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;

/**
 * Test data only. Never used to create real accounts (see `php artisan admin:create`).
 *
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    public const PASSWORD = 'test-only-correct-horse-battery';

    protected static ?string $passwordHash = null;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$passwordHash ??= Hash::make(self::PASSWORD),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function withTwoFactor(): static
    {
        return $this->afterCreating(function (User $user) {
            $user->forceFill([
                'two_factor_secret' => (new Google2FA)->generateSecretKey(32),
                'two_factor_confirmed_at' => now(),
            ])->save();
        });
    }
}
