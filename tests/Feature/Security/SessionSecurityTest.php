<?php

namespace Tests\Feature\Security;

use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class SessionSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_session_cookie_is_http_only_and_same_site(): void
    {
        $cookie = collect($this->get($this->adminUrl('login'))->headers->getCookies())
            ->first(fn ($c) => $c->getName() === config('session.cookie'));

        $this->assertNotNull($cookie);
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('lax', $cookie->getSameSite());
    }

    public function test_database_sessions_store_no_ip_address_or_user_agent(): void
    {
        config(['session.driver' => 'database']);
        $user = $this->createUser(withTwoFactor: false);

        $this->withHeader('User-Agent', 'TestBrowser/1.0')
            ->post($this->adminUrl('login'), ['email' => $user->email, 'password' => UserFactory::PASSWORD]);

        $this->assertFalse(Schema::hasColumn('sessions', 'ip_address'));
        $this->assertFalse(Schema::hasColumn('sessions', 'user_agent'));
        $this->assertSame(1, DB::table('sessions')->where('user_id', $user->id)->count());
    }

    public function test_previous_session_is_destroyed_on_every_privilege_change(): void
    {
        config(['session.driver' => 'database']);
        $user = $this->createUser(withTwoFactor: false);

        // Anonymous session -> authenticated (password only).
        $this->get($this->adminUrl('login'));
        $anonymousId = session()->getId();
        $this->withSessionCookie()->post($this->adminUrl('login'), ['email' => $user->email, 'password' => UserFactory::PASSWORD]);
        $passwordOnlyId = session()->getId();
        $this->assertNotSame($anonymousId, $passwordOnlyId);
        $this->assertDatabaseMissing('sessions', ['id' => $anonymousId]);

        // Password-only session -> MFA-verified session (enrolment).
        $this->withSessionCookie()->get($this->adminUrl('konto/zwei-faktor'))->assertOk();
        $this->assertSame($passwordOnlyId, session()->getId());
        $code = (new Google2FA)->getCurrentOtp((string) $user->refresh()->two_factor_secret);
        $this->withSessionCookie()->post($this->adminUrl('konto/zwei-faktor'), ['code' => $code])->assertOk();

        $this->assertNotSame($passwordOnlyId, session()->getId());
        $this->assertDatabaseMissing('sessions', ['id' => $passwordOnlyId]);
        $this->assertSame(1, DB::table('sessions')->where('user_id', $user->id)->count());
    }

    public function test_pre_challenge_session_is_destroyed_when_mfa_challenge_starts(): void
    {
        config(['session.driver' => 'database']);
        $user = $this->createUser();

        $this->get($this->adminUrl('login'));
        $anonymousId = session()->getId();
        $this->withSessionCookie()
            ->post($this->adminUrl('login'), ['email' => $user->email, 'password' => UserFactory::PASSWORD])
            ->assertRedirect($this->adminUrl('login/zwei-faktor'));

        $this->assertNotSame($anonymousId, session()->getId());
        $this->assertDatabaseMissing('sessions', ['id' => $anonymousId]);
    }

    public function test_production_session_cookie_defaults(): void
    {
        $config = (function () {
            putenv('APP_ENV=production');
            $_ENV['APP_ENV'] = $_SERVER['APP_ENV'] = 'production';

            try {
                return require base_path('config/session.php');
            } finally {
                putenv('APP_ENV=testing');
                $_ENV['APP_ENV'] = $_SERVER['APP_ENV'] = 'testing';
            }
        })();

        $this->assertTrue($config['secure']);
        $this->assertTrue($config['http_only']);
        $this->assertStringStartsWith('__Host-', $config['cookie']);
        $this->assertTrue($config['encrypt']);
    }
}
