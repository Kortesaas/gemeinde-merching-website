<?php

namespace Tests\Feature\Admin;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private const NEW_PASSWORD = 'ein-ganz-neues-passwort-2026';

    public function test_forgot_password_page_is_accessible(): void
    {
        $this->get($this->adminUrl('passwort-vergessen'))
            ->assertOk()
            ->assertSee('autocomplete="username"', false)
            ->assertSee('<h1>Passwort vergessen</h1>', false);
    }

    public function test_reset_link_is_sent_to_active_account_with_generic_response(): void
    {
        Notification::fake();
        $user = $this->createUser();

        $this->from($this->adminUrl('passwort-vergessen'))
            ->post($this->adminUrl('passwort-vergessen'), ['email' => $user->email])
            ->assertSessionHas('status', __('passwords.sent'));

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
            $url = $notification->toMail($user)->actionUrl;

            return str_contains($url, $this->adminUrl('passwort-zuruecksetzen/'));
        });
    }

    public function test_unknown_and_inactive_accounts_get_identical_response_and_no_mail(): void
    {
        Notification::fake();
        $inactive = $this->createUser();
        $inactive->forceFill(['is_active' => false])->save();

        foreach (['niemand@example.test', $inactive->email] as $email) {
            $this->from($this->adminUrl('passwort-vergessen'))
                ->post($this->adminUrl('passwort-vergessen'), ['email' => $email])
                ->assertSessionHasNoErrors()
                ->assertSessionHas('status', __('passwords.sent'));
        }

        Notification::assertNothingSent();
    }

    public function test_password_can_be_reset_with_valid_token_and_sessions_are_ended(): void
    {
        $user = $this->createUser();
        $token = Password::broker()->createToken($user);
        DB::table('sessions')->insert(['id' => 'old-session', 'user_id' => $user->id, 'payload' => 'x', 'last_activity' => time()]);

        $this->get($this->adminUrl('passwort-zuruecksetzen/'.$token).'?email='.urlencode($user->email))
            ->assertOk()
            ->assertSee('autocomplete="new-password"', false);

        $this->post($this->adminUrl('passwort-zuruecksetzen'), [
            'token' => $token,
            'email' => $user->email,
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ])->assertRedirect($this->adminUrl('login'));

        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $user->refresh()->password));
        $this->assertDatabaseMissing('sessions', ['id' => 'old-session']);
        $this->assertDatabaseHas('audit_events', ['action' => 'auth.password_reset', 'subject_id' => $user->id]);
        // Not logged in automatically: password + second factor are still required.
        $this->assertGuest();
    }

    public function test_reset_token_expires(): void
    {
        $user = $this->createUser();
        $token = Password::broker()->createToken($user);

        $this->travel((int) config('auth.passwords.users.expire') + 1)->minutes();

        $this->from($this->adminUrl('passwort-zuruecksetzen/'.$token))
            ->post($this->adminUrl('passwort-zuruecksetzen'), [
                'token' => $token,
                'email' => $user->email,
                'password' => self::NEW_PASSWORD,
                'password_confirmation' => self::NEW_PASSWORD,
            ])->assertSessionHasErrors(['email' => __('passwords.token')]);

        $this->assertFalse(Hash::check(self::NEW_PASSWORD, $user->refresh()->password));
    }

    public function test_reset_token_can_only_be_used_once(): void
    {
        $user = $this->createUser();
        $token = Password::broker()->createToken($user);
        $payload = ['token' => $token, 'email' => $user->email, 'password' => self::NEW_PASSWORD, 'password_confirmation' => self::NEW_PASSWORD];

        $this->post($this->adminUrl('passwort-zuruecksetzen'), $payload)->assertRedirect($this->adminUrl('login'));
        $this->post($this->adminUrl('passwort-zuruecksetzen'), $payload)->assertSessionHasErrors('email');
    }

    public function test_weak_passwords_are_rejected(): void
    {
        $user = $this->createUser();
        $token = Password::broker()->createToken($user);

        $this->post($this->adminUrl('passwort-zuruecksetzen'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'kurz',
            'password_confirmation' => 'kurz',
        ])->assertSessionHasErrors('password');
    }

    public function test_reset_requests_are_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post($this->adminUrl('passwort-vergessen'), ['email' => "x{$i}@example.test"]);
        }

        $this->post($this->adminUrl('passwort-vergessen'), ['email' => 'y@example.test'])->assertStatus(429);
    }
}
