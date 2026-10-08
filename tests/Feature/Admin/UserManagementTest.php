<?php

namespace Tests\Feature\Admin;

use App\Models\AuditEvent;
use App\Models\User;
use App\Support\Authorization\Role;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_creates_account_and_person_sets_own_password(): void
    {
        Notification::fake();
        $admin = $this->createUser();

        $this->actingAsAdmin($admin)->post($this->adminUrl('benutzer'), [
            'name' => 'Neue Redakteurin', 'email' => 'redaktion@example.test', 'roles' => [Role::Fachbereichsredaktion->value],
        ])->assertSessionHasNoErrors();

        $user = User::query()->where('email', 'redaktion@example.test')->firstOrFail();
        $this->assertTrue($user->hasRole(Role::Fachbereichsredaktion->value));
        $this->assertFalse($user->hasEnabledTwoFactor());
        Notification::assertSentTo($user, ResetPassword::class);
        $this->assertDatabaseHas('audit_events', ['action' => 'user.created', 'subject_id' => $user->id]);
    }

    public function test_role_changes_are_audited(): void
    {
        $admin = $this->createUser();
        $editor = $this->createUser(Role::Fachbereichsredaktion);

        $this->actingAsAdmin($admin)->put($this->adminUrl('benutzer/'.$editor->id), [
            'name' => $editor->name, 'email' => $editor->email, 'roles' => [Role::Chefredaktion->value], 'is_active' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertTrue($editor->fresh()?->hasRole(Role::Chefredaktion->value));
        $event = AuditEvent::query()->where('action', 'user.roles_changed')->firstOrFail();
        $this->assertSame(['chefredaktion'], $event->metadata['added'] ?? null);
        $this->assertSame(['fachbereichsredaktion'], $event->metadata['removed'] ?? null);
    }

    public function test_administrator_cannot_lock_themselves_out(): void
    {
        $admin = $this->createUser();

        $this->actingAsAdmin($admin)->put($this->adminUrl('benutzer/'.$admin->id), [
            'name' => $admin->name, 'email' => $admin->email, 'roles' => [Role::Reviewer->value], 'is_active' => '1',
        ])->assertSessionHasErrors('roles');

        $this->assertTrue($admin->fresh()?->hasRole(Role::Administrator->value));
    }

    public function test_last_active_administrator_is_protected(): void
    {
        $actor = $this->userWithPermissionsOnly(['user.view', 'user.edit']);
        $onlyAdmin = $this->createUser();

        $this->actingAsAdmin($actor)->put($this->adminUrl('benutzer/'.$onlyAdmin->id), [
            'name' => $onlyAdmin->name, 'email' => $onlyAdmin->email, 'roles' => [Role::Administrator->value], 'is_active' => '0',
        ])->assertSessionHasErrors('roles');
    }

    public function test_non_administrators_cannot_manage_accounts(): void
    {
        $chief = $this->createUser(Role::Chefredaktion);

        $this->actingAsAdmin($chief)->get($this->adminUrl('benutzer'))->assertForbidden();
        $this->actingAsAdmin($chief)->post($this->adminUrl('benutzer'), ['name' => 'X', 'email' => 'x@example.test', 'roles' => ['administrator']])->assertForbidden();
        $this->assertDatabaseMissing('users', ['email' => 'x@example.test']);
    }

    /**
     * @param  list<string>  $permissions
     */
    private function userWithPermissionsOnly(array $permissions): User
    {
        $user = $this->createUser(role: null);
        $user->givePermissionTo(['admin.access', ...$permissions]);

        return $user->refresh();
    }
}
