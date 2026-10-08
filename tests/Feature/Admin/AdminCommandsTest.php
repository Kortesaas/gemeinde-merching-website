<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Support\Authorization\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminCommandsTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'sehr-sicheres-start-passwort';

    public function test_admin_create_prompts_for_everything_and_assigns_administrator_role(): void
    {
        $this->artisan('admin:create')
            ->expectsQuestion('Name', 'Erika Mustermann')
            ->expectsQuestion('E-Mail-Adresse (Anmeldename)', 'Erika@Example.TEST')
            ->expectsQuestion('Passwort (mind. 12 Zeichen)', self::PASSWORD)
            ->expectsQuestion('Passwort wiederholen', self::PASSWORD)
            ->assertSuccessful();

        $user = User::query()->where('email', 'erika@example.test')->firstOrFail();
        $this->assertTrue(Hash::check(self::PASSWORD, $user->password));
        $this->assertTrue($user->hasRole(Role::Administrator->value));
        $this->assertFalse($user->hasEnabledTwoFactor(), 'MFA is set up by the user at first login.');
        $this->assertDatabaseHas('audit_events', ['action' => 'user.created', 'subject_id' => $user->id]);
    }

    public function test_admin_create_refuses_non_interactive_mode(): void
    {
        $this->artisan('admin:create', ['--no-interaction' => true])->assertFailed();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_no_default_accounts_are_seeded(): void
    {
        $this->seed();

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('roles', count(Role::cases()));
    }

    public function test_reset_mfa_removes_second_factor_and_sessions(): void
    {
        $user = $this->createUser();
        \DB::table('sessions')->insert(['id' => 's1', 'user_id' => $user->id, 'payload' => 'x', 'last_activity' => time()]);

        $this->artisan('admin:reset-mfa', ['email' => $user->email])
            ->expectsConfirmation("Remove two-factor authentication for {$user->email}? Only do this after verifying the person's identity.", 'yes')
            ->assertSuccessful();

        $this->assertFalse($user->refresh()->hasEnabledTwoFactor());
        $this->assertDatabaseMissing('sessions', ['id' => 's1']);
    }
}
