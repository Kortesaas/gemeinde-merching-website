<?php

namespace Tests\Feature\Security;

use App\Providers\AppServiceProvider;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

class ErrorHandlingTest extends TestCase
{
    public function test_debug_mode_is_forced_off_in_production(): void
    {
        $this->app['env'] = 'production';
        config(['app.debug' => true, 'session.secure' => false]);

        (new AppServiceProvider($this->app))->register();

        $this->assertFalse(config('app.debug'));
        $this->assertTrue(config('session.secure'));
        $this->assertTrue(config('session.http_only'));
    }

    public function test_server_errors_do_not_expose_internals(): void
    {
        config(['app.debug' => false]);
        Route::middleware('public')->get('/_test/boom', function () {
            throw new RuntimeException('SQLSTATE[42S02] secret-table /var/www/html/app/Secret.php');
        });

        $response = $this->get('/_test/boom');

        $response->assertStatus(500)->assertSee('Technischer Fehler');
        foreach (['SQLSTATE', 'secret-table', '/var/www', 'Secret.php', 'RuntimeException', 'Stack trace'] as $leak) {
            $response->assertDontSee($leak, false);
        }
    }

    public function test_json_errors_do_not_expose_internals(): void
    {
        config(['app.debug' => false]);
        Route::middleware('public')->get('/_test/boom-json', fn () => throw new RuntimeException('internal detail'));

        $this->getJson('/_test/boom-json')
            ->assertStatus(500)
            ->assertExactJson(['message' => 'Server Error']);
    }
}
