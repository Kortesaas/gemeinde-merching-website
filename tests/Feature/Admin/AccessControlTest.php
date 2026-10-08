<?php

namespace Tests\Feature\Admin;

use App\Support\Authorization\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{string}>
     */
    public static function protectedPaths(): array
    {
        return [
            'backend root' => [''],
            'dashboard' => ['dashboard'],
            'mfa setup' => ['konto/zwei-faktor'],
        ];
    }

    #[DataProvider('protectedPaths')]
    public function test_anonymous_users_are_redirected_to_login(string $path): void
    {
        $this->get($this->adminUrl($path))->assertRedirect($this->adminUrl('login'));
    }

    public function test_anonymous_post_to_protected_route_is_rejected(): void
    {
        $this->post($this->adminUrl('logout'))->assertRedirect($this->adminUrl('login'));
        $this->post($this->adminUrl('konto/zwei-faktor/wiederherstellungscodes'))->assertRedirect($this->adminUrl('login'));
    }

    public function test_authorized_user_can_access_dashboard(): void
    {
        $user = $this->createUser(Role::Administrator);

        $this->actingAsAdmin($user)
            ->get($this->adminUrl('dashboard'))
            ->assertOk()
            ->assertSee('Backend foundation operational')
            ->assertSee($user->name);
    }

    public function test_backend_root_redirects_to_dashboard(): void
    {
        $this->actingAsAdmin($this->createUser())
            ->get($this->adminUrl())
            ->assertRedirect($this->adminUrl('dashboard'));
    }

    public function test_user_without_any_role_is_forbidden(): void
    {
        $user = $this->createUser(role: null);

        $this->actingAsAdmin($user)->get($this->adminUrl('dashboard'))->assertForbidden();
    }

    public function test_every_editorial_role_can_enter_the_backend(): void
    {
        foreach (Role::cases() as $role) {
            $this->actingAsAdmin($this->createUser($role))
                ->get($this->adminUrl('dashboard'))
                ->assertOk();
        }
    }

    public function test_public_registration_does_not_exist(): void
    {
        foreach (['/register', '/registrieren', $this->adminUrl('register'), $this->adminUrl('registrieren')] as $path) {
            $this->get($path)->assertNotFound();
            $this->post($path)->assertNotFound();
        }
    }

    public function test_deactivated_user_session_is_terminated(): void
    {
        $user = $this->createUser();
        $user->forceFill(['is_active' => false])->save();

        $this->actingAsAdmin($user)
            ->get($this->adminUrl('dashboard'))
            ->assertRedirect($this->adminUrl('login'));

        $this->assertGuest();
    }

    public function test_absolute_session_lifetime_is_enforced(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)->withSession([
            'admin.authenticated_at' => now()->subMinutes((int) config('admin.session.absolute_lifetime') + 1)->getTimestamp(),
            'admin.mfa_verified' => true,
        ])->get($this->adminUrl('dashboard'))->assertRedirect($this->adminUrl('login'));

        $this->assertGuest();
    }

    public function test_mfa_enrolled_user_without_verified_session_is_logged_out(): void
    {
        $user = $this->createUser();

        $this->actingAsAdmin($user, mfaVerified: false)
            ->get($this->adminUrl('dashboard'))
            ->assertRedirect($this->adminUrl('login'));

        $this->assertGuest();
    }
}
