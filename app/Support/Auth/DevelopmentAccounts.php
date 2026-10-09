<?php

namespace App\Support\Auth;

/**
 * Accounts created by the development demo seeder use the reserved
 * "demo.localhost" domain. They may only exist and sign in locally:
 * creation, login and deploy:check refuse them everywhere else.
 */
final class DevelopmentAccounts
{
    public const DOMAIN = 'demo.localhost';

    public static function isDevelopmentAccount(?string $email): bool
    {
        return str_ends_with(mb_strtolower(trim((string) $email)), '@'.self::DOMAIN);
    }

    public static function allowed(): bool
    {
        return app()->environment(['local', 'development', 'testing']);
    }
}
