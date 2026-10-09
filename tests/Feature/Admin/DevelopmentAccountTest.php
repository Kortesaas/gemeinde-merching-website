<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Services\Auth\AdminAuthenticator;
use Database\Seeders\DevelopmentDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;
use RuntimeException;
use Tests\TestCase;

/** The documented demo administrator works locally and nowhere else. */
class DevelopmentAccountTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake((string) config('uploads.disk'));
        $this->seed(DevelopmentDemoSeeder::class);
    }

    public function test_demo_admin_signs_in_locally_and_must_pass_the_second_factor(): void
    {
        $this->post($this->adminUrl('login'), ['email' => DevelopmentDemoSeeder::ADMIN_EMAIL, 'password' => DevelopmentDemoSeeder::ADMIN_PASSWORD])
            ->assertRedirect($this->adminUrl('login/zwei-faktor'));
        // The documented TOTP secret completes the second factor.
        $code = (new Google2FA)->getCurrentOtp(DevelopmentDemoSeeder::ADMIN_TOTP_SECRET);
        $this->post($this->adminUrl('login/zwei-faktor'), ['code' => $code])->assertRedirect($this->adminUrl('dashboard'));
        $this->assertAuthenticated();
    }

    public function test_demo_accounts_cannot_sign_in_outside_local_environments(): void
    {
        $this->app['env'] = 'production';

        $this->expectException(ValidationException::class);
        app(AdminAuthenticator::class)->validateCredentials(Request::create('/verwaltung/login', 'POST'), DevelopmentDemoSeeder::ADMIN_EMAIL, DevelopmentDemoSeeder::ADMIN_PASSWORD);
    }

    public function test_demo_accounts_cannot_be_created_outside_local_environments(): void
    {
        $this->app['env'] = 'production';

        $this->expectException(RuntimeException::class);
        User::create(['name' => 'Versuch', 'email' => 'neu@demo.localhost', 'password' => 'irrelevant-password-123', 'is_active' => true]);
    }

    public function test_deploy_check_fails_while_demo_accounts_exist(): void
    {
        Artisan::call('deploy:check');
        $output = Artisan::output();
        $this->assertMatchesRegularExpression('/No development demo accounts[ .]+FAILED/', $output);
    }
}
