<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Services\Auth\TwoFactorAuthenticator;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private function currentCode(User $user): string
    {
        return (new Google2FA)->getCurrentOtp((string) $user->refresh()->two_factor_secret);
    }

    private function loginWithPassword(User $user): void
    {
        $this->post($this->adminUrl('login'), ['email' => $user->email, 'password' => UserFactory::PASSWORD])
            ->assertRedirect($this->adminUrl('login/zwei-faktor'));
    }

    public function test_enrolment_flow_with_qr_code_and_recovery_codes(): void
    {
        $user = $this->createUser(withTwoFactor: false);
        $this->post($this->adminUrl('login'), ['email' => $user->email, 'password' => UserFactory::PASSWORD]);

        $page = $this->get($this->adminUrl('konto/zwei-faktor'))->assertOk();
        $page->assertSee('src="data:image/svg+xml;base64,', false)
            ->assertSee('alt="QR-Code', false)
            ->assertSee('autocomplete="one-time-code"', false);

        $user->refresh();
        $this->assertNotNull($user->two_factor_secret);
        $this->assertNull($user->two_factor_confirmed_at);
        // Stored encrypted, never in plain text.
        $raw = (string) \DB::table('users')->where('id', $user->id)->value('two_factor_secret');
        $this->assertStringNotContainsString((string) $user->two_factor_secret, $raw);

        $response = $this->post($this->adminUrl('konto/zwei-faktor'), ['code' => $this->currentCode($user)])
            ->assertOk()
            ->assertSee('Ihre Wiederherstellungscodes');

        $this->assertTrue($user->refresh()->hasEnabledTwoFactor());
        $this->assertSame(10, $user->recoveryCodes()->count());

        // Codes are shown once and stored only as hashes.
        preg_match_all('/<code>([A-Z0-9]{5}-[A-Z0-9]{5})<\/code>/', (string) $response->getContent(), $m);
        $this->assertCount(10, $m[1]);
        $this->assertDatabaseMissing('two_factor_recovery_codes', ['code_hash' => $m[1][0]]);

        $this->get($this->adminUrl('dashboard'))->assertOk();
    }

    public function test_enrolment_rejects_wrong_code(): void
    {
        $user = $this->createUser(withTwoFactor: false);
        $this->actingAsAdmin($user, mfaVerified: false)->get($this->adminUrl('konto/zwei-faktor'));

        $this->post($this->adminUrl('konto/zwei-faktor'), ['code' => '000000'])->assertSessionHasErrors('code');
        $this->assertFalse($user->refresh()->hasEnabledTwoFactor());
    }

    public function test_password_alone_does_not_authenticate_mfa_user(): void
    {
        $user = $this->createUser();

        $this->loginWithPassword($user);

        $this->assertGuest();
        $this->get($this->adminUrl('dashboard'))->assertRedirect($this->adminUrl('login'));
    }

    public function test_valid_totp_code_completes_login_and_regenerates_session(): void
    {
        $user = $this->createUser();
        $this->loginWithPassword($user);
        $sessionId = session()->getId();

        $this->withSessionCookie()->get($this->adminUrl('login/zwei-faktor'))->assertOk()->assertSee('autocomplete="one-time-code"', false);
        $this->assertSame($sessionId, session()->getId());

        $this->withSessionCookie()->post($this->adminUrl('login/zwei-faktor'), ['code' => $this->currentCode($user)])
            ->assertRedirect($this->adminUrl('dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($sessionId, session()->getId());
        $this->get($this->adminUrl('dashboard'))->assertOk();
    }

    public function test_totp_code_cannot_be_replayed(): void
    {
        $user = $this->createUser();
        $code = $this->currentCode($user);

        $this->loginWithPassword($user);
        $this->post($this->adminUrl('login/zwei-faktor'), ['code' => $code]);
        $this->post($this->adminUrl('logout'));

        $this->loginWithPassword($user);
        $this->post($this->adminUrl('login/zwei-faktor'), ['code' => $code])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_wrong_codes_are_rate_limited(): void
    {
        $user = $this->createUser();
        $this->loginWithPassword($user);

        for ($i = 0; $i < (int) config('admin.throttle.mfa_attempts'); $i++) {
            $this->post($this->adminUrl('login/zwei-faktor'), ['code' => '000000'])->assertSessionHasErrors('code');
        }

        $this->post($this->adminUrl('login/zwei-faktor'), ['code' => $this->currentCode($user)])
            ->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_recovery_code_can_be_used_exactly_once(): void
    {
        $user = $this->createUser();
        $codes = app(TwoFactorAuthenticator::class)->regenerateRecoveryCodes($user);

        $this->loginWithPassword($user);
        $this->post($this->adminUrl('login/wiederherstellungscode'), ['recovery_code' => strtolower($codes[0])])
            ->assertRedirect($this->adminUrl('dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('audit_events', ['action' => 'auth.recovery_code_used', 'subject_id' => $user->id]);

        $this->post($this->adminUrl('logout'));

        $this->loginWithPassword($user);
        $this->post($this->adminUrl('login/wiederherstellungscode'), ['recovery_code' => $codes[0]])
            ->assertSessionHasErrors('recovery_code');
        $this->assertGuest();
    }

    public function test_challenge_expires(): void
    {
        $user = $this->createUser();
        $this->loginWithPassword($user);

        $this->travel((int) config('admin.mfa.challenge_timeout') + 1)->seconds();

        $this->post($this->adminUrl('login/zwei-faktor'), ['code' => $this->currentCode($user)])
            ->assertRedirect($this->adminUrl('login'));
        $this->assertGuest();
    }

    public function test_challenge_page_without_pending_login_redirects(): void
    {
        $this->get($this->adminUrl('login/zwei-faktor'))->assertRedirect($this->adminUrl('login'));
    }

    public function test_recovery_codes_can_be_regenerated_with_current_password(): void
    {
        $user = $this->createUser();
        $twoFactor = app(TwoFactorAuthenticator::class);
        $old = $twoFactor->regenerateRecoveryCodes($user);

        $this->actingAsAdmin($user)
            ->post($this->adminUrl('konto/zwei-faktor/wiederherstellungscodes'), ['current_password' => 'falsch-falsch-falsch'])
            ->assertSessionHasErrors('current_password');

        $this->actingAsAdmin($user)
            ->post($this->adminUrl('konto/zwei-faktor/wiederherstellungscodes'), ['current_password' => UserFactory::PASSWORD])
            ->assertOk()
            ->assertSee('Ihre Wiederherstellungscodes');

        $this->assertFalse($twoFactor->useRecoveryCode($user, $old[0]));
    }

    public function test_mfa_can_be_made_optional_by_configuration(): void
    {
        config(['admin.mfa.required' => false]);
        $user = $this->createUser(withTwoFactor: false);

        $this->actingAsAdmin($user, mfaVerified: false)->get($this->adminUrl('dashboard'))->assertOk();
    }
}
