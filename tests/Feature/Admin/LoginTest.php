<?php

namespace Tests\Feature\Admin;

use App\Models\AuditEvent;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_is_accessible_markup(): void
    {
        $html = (string) $this->get($this->adminUrl('login'))->assertOk()->getContent();

        $this->assertStringContainsString('<html lang="de">', $html);
        $this->assertStringContainsString('<title>Anmelden – Verwaltung – Gemeinde Merching</title>', $html);
        $this->assertStringContainsString('<label class="form-label" for="email">E-Mail-Adresse</label>', $html);
        $this->assertStringContainsString('<label class="form-label" for="password">Passwort</label>', $html);
        $this->assertMatchesRegularExpression('/<input[^>]*autocomplete="username"[^>]*id="email"/s', $html);
        $this->assertMatchesRegularExpression('/<input[^>]*autocomplete="current-password"[^>]*id="password"/s', $html);
        $this->assertStringContainsString('<button type="submit" class="button">Anmelden</button>', $html);
        $this->assertStringNotContainsString('placeholder=', $html);
        $this->assertStringNotContainsString('onpaste', $html);
        $this->assertStringNotContainsString('<script', $html);
    }

    public function test_user_without_mfa_logs_in_and_must_enrol(): void
    {
        $user = $this->createUser(withTwoFactor: false);

        $this->post($this->adminUrl('login'), ['email' => $user->email, 'password' => UserFactory::PASSWORD])
            ->assertRedirect($this->adminUrl('dashboard'));

        $this->assertAuthenticatedAs($user);

        // MFA is required: the dashboard sends the user to enrolment.
        $this->get($this->adminUrl('dashboard'))->assertRedirect($this->adminUrl('konto/zwei-faktor'));
    }

    public function test_email_is_case_insensitive(): void
    {
        $user = $this->createUser(withTwoFactor: false);

        $this->post($this->adminUrl('login'), ['email' => strtoupper($user->email), 'password' => UserFactory::PASSWORD]);

        $this->assertAuthenticatedAs($user);
    }

    public function test_session_id_is_regenerated_on_login(): void
    {
        $user = $this->createUser(withTwoFactor: false);

        $this->get($this->adminUrl('login'));
        $before = session()->getId();
        $tokenBefore = session()->token();

        $this->withSessionCookie()->post($this->adminUrl('login'), ['email' => $user->email, 'password' => UserFactory::PASSWORD]);

        $this->assertNotSame($before, session()->getId());
        $this->assertNotSame($tokenBefore, session()->token());
    }

    public function test_wrong_password_and_unknown_account_get_the_same_generic_error(): void
    {
        $user = $this->createUser(withTwoFactor: false);

        $wrongPassword = $this->from($this->adminUrl('login'))
            ->post($this->adminUrl('login'), ['email' => $user->email, 'password' => 'wrong-password-123']);
        $unknown = $this->from($this->adminUrl('login'))
            ->post($this->adminUrl('login'), ['email' => 'niemand@example.test', 'password' => 'wrong-password-123']);

        $wrongPassword->assertRedirect($this->adminUrl('login'))->assertSessionHasErrors(['email' => __('auth.failed')]);
        $unknown->assertRedirect($this->adminUrl('login'))->assertSessionHasErrors(['email' => __('auth.failed')]);
        $this->assertGuest();
    }

    public function test_inactive_account_cannot_log_in(): void
    {
        $user = $this->createUser(withTwoFactor: false);
        $user->forceFill(['is_active' => false])->save();

        $this->post($this->adminUrl('login'), ['email' => $user->email, 'password' => UserFactory::PASSWORD])
            ->assertSessionHasErrors(['email' => __('auth.failed')]);

        $this->assertGuest();
    }

    public function test_login_is_rate_limited_per_account(): void
    {
        $user = $this->createUser(withTwoFactor: false);

        for ($i = 0; $i < (int) config('admin.throttle.login_attempts'); $i++) {
            $this->post($this->adminUrl('login'), ['email' => $user->email, 'password' => 'wrong-password-'.$i]);
        }

        // Even the correct password is rejected during the lockout.
        $this->post($this->adminUrl('login'), ['email' => $user->email, 'password' => UserFactory::PASSWORD])
            ->assertSessionHasErrors('email');

        $this->assertStringContainsString('Zu viele Versuche', session('errors')->first('email'));
        $this->assertGuest();
    }

    public function test_login_is_rate_limited_per_client_across_accounts(): void
    {
        $user = $this->createUser(withTwoFactor: false);

        for ($i = 0; $i < (int) config('admin.throttle.login_attempts_per_client'); $i++) {
            $this->post($this->adminUrl('login'), ['email' => "unbekannt{$i}@example.test", 'password' => 'irrelevant-pw']);
        }

        $this->post($this->adminUrl('login'), ['email' => $user->email, 'password' => UserFactory::PASSWORD])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_logout_invalidates_session(): void
    {
        $user = $this->createUser();

        $this->actingAsAdmin($user)->get($this->adminUrl('dashboard'))->assertOk();
        $sessionId = session()->getId();

        $this->withSessionCookie()->post($this->adminUrl('logout'))->assertRedirect($this->adminUrl('login'));

        $this->assertGuest();
        $this->assertNotSame($sessionId, session()->getId());
        $this->get($this->adminUrl('dashboard'))->assertRedirect($this->adminUrl('login'));
    }

    public function test_logout_requires_post(): void
    {
        $user = $this->createUser();
        $this->actingAsAdmin($user)->get($this->adminUrl('logout'))->assertNotFound();
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_and_failure_are_audited_without_network_data(): void
    {
        $user = $this->createUser(withTwoFactor: false);

        $this->post($this->adminUrl('login'), ['email' => $user->email, 'password' => 'wrong-password-123']);
        $this->post($this->adminUrl('login'), ['email' => $user->email, 'password' => UserFactory::PASSWORD]);

        $this->assertDatabaseHas('audit_events', ['action' => 'auth.login_failed', 'subject_id' => $user->id, 'user_id' => null]);
        $this->assertDatabaseHas('audit_events', ['action' => 'auth.login', 'user_id' => $user->id]);

        foreach (AuditEvent::all() as $event) {
            $json = json_encode($event->toArray());
            $this->assertStringNotContainsString('127.0.0.1', (string) $json);
            $this->assertStringNotContainsString(UserFactory::PASSWORD, (string) $json);
        }
    }

    public function test_authenticated_user_visiting_login_is_redirected(): void
    {
        $this->actingAsAdmin($this->createUser())
            ->get($this->adminUrl('login'))
            ->assertRedirect($this->adminUrl('dashboard'));
    }
}
