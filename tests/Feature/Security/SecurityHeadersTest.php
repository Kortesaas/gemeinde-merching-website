<?php

namespace Tests\Feature\Security;

use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Behave like production.
        $this->app['env'] = 'production';
    }

    public function test_strict_content_security_policy_in_production(): void
    {
        $csp = (string) $this->get('/')->headers->get('Content-Security-Policy');

        foreach ([
            "default-src 'self'",
            "script-src 'self'",
            "style-src 'self'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
            'upgrade-insecure-requests',
        ] as $directive) {
            $this->assertStringContainsString($directive, $csp);
        }

        $this->assertStringNotContainsString('unsafe-inline', $csp);
        $this->assertStringNotContainsString('unsafe-eval', $csp);
    }

    public function test_baseline_security_headers_on_public_and_admin_responses(): void
    {
        foreach (['/', $this->adminUrl('login')] as $path) {
            $response = $this->get($path);

            $response->assertHeader('X-Content-Type-Options', 'nosniff')
                ->assertHeader('X-Frame-Options', 'DENY')
                ->assertHeader('Cross-Origin-Opener-Policy', 'same-origin')
                ->assertHeader('Permissions-Policy');
            $this->assertCount(1, $response->headers->all('referrer-policy'), $path);
        }

        $this->get('/')->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_hsts_only_over_https_in_production(): void
    {
        $this->get('https://localhost/')->assertHeader('Strict-Transport-Security', 'max-age=31536000');
        $this->get('http://localhost/')->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_hsts_not_sent_outside_production(): void
    {
        $this->app['env'] = 'local';

        $this->get('https://localhost/')->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_http_is_redirected_to_https_when_enforced(): void
    {
        config([
            'security.force_https' => true,
            'security.trusted_hosts' => ['www.example.test'],
            'app.url' => 'https://www.example.test',
        ]);

        $this->get('http://www.example.test/verwaltung/login?x=1')
            ->assertStatus(301)
            ->assertRedirect('https://www.example.test/verwaltung/login?x=1');
    }

    public function test_untrusted_host_header_is_rejected(): void
    {
        config(['security.trusted_hosts' => ['www.example.test'], 'app.url' => 'https://www.example.test']);

        // Protects generated absolute URLs (e.g. password-reset links) against Host-header injection.
        $this->get('https://evil.example/verwaltung/passwort-vergessen')->assertStatus(400);
        $this->get('https://www.example.test/verwaltung/passwort-vergessen')->assertOk();
    }

    public function test_admin_responses_are_not_cacheable_and_not_indexable(): void
    {
        foreach ([$this->adminUrl('login'), $this->adminUrl('passwort-vergessen'), $this->adminUrl('unbekannt')] as $path) {
            $response = $this->get($path);

            $cacheControl = (string) $response->headers->get('Cache-Control');
            $this->assertStringContainsString('no-store', $cacheControl, $path);
            $this->assertStringContainsString('private', $cacheControl, $path);
            $response->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
        }
    }

    public function test_authenticated_admin_pages_are_not_cacheable(): void
    {
        $this->app['env'] = 'testing';
        $this->artisan('migrate:fresh');
        $user = $this->createUser();

        $response = $this->actingAsAdmin($user)->get($this->adminUrl('dashboard'))->assertOk();

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $response->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
    }

    public function test_server_does_not_advertise_php(): void
    {
        $this->get('/')->assertHeaderMissing('X-Powered-By');
    }
}
