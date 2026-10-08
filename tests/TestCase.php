<?php

namespace Tests;

use App\Models\User;
use App\Services\Auth\AdminAuthenticator;
use App\Services\Authorization\RoleSynchronizer;
use App\Support\Authorization\Role;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Http\Request;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Feature tests do not depend on compiled frontend assets.
        $this->withoutVite();
    }

    protected function tearDown(): void
    {
        // Trusted host patterns are static in Symfony's Request; never leak them between tests.
        Request::setTrustedHosts([]);

        parent::tearDown();
    }

    /**
     * Create a user with a role (roles are synchronised first).
     */
    protected function createUser(?Role $role = Role::Administrator, bool $withTwoFactor = true): User
    {
        $this->app->make(RoleSynchronizer::class)->sync();

        $factory = User::factory();
        $user = ($withTwoFactor ? $factory->withTwoFactor() : $factory)->create();

        if ($role !== null) {
            $user->assignRole($role->value);
        }

        return $user->refresh();
    }

    /**
     * Act as a fully authenticated backend user (password + second factor).
     */
    protected function actingAsAdmin(User $user, bool $mfaVerified = true): static
    {
        return $this->actingAs($user)->withSession([
            AdminAuthenticator::AUTHENTICATED_AT => now()->getTimestamp(),
            AdminAuthenticator::MFA_VERIFIED => $mfaVerified,
        ]);
    }

    /**
     * Send the current session cookie with the next request, like a browser.
     * (Laravel's test client does not do this automatically, which would make
     * every request start a new session ID.)
     */
    protected function withSessionCookie(): static
    {
        return $this->withCookie((string) config('session.cookie'), session()->getId());
    }

    protected function adminUrl(string $path = ''): string
    {
        return '/'.config('admin.path').($path === '' ? '' : '/'.ltrim($path, '/'));
    }
}
