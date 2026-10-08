<?php

namespace Tests\Feature\Security;

use Tests\TestCase;

class CsrfProtectionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Laravel skips CSRF checks in the "testing" environment; switch it off.
        $this->app['env'] = 'local';
    }

    public function test_post_without_token_is_rejected(): void
    {
        $this->post($this->adminUrl('login'), ['email' => 'a@example.test', 'password' => 'x'])
            ->assertStatus(419);
    }

    public function test_cross_site_post_is_rejected(): void
    {
        $this->withHeader('Sec-Fetch-Site', 'cross-site')
            ->post($this->adminUrl('login'), ['email' => 'a@example.test', 'password' => 'x', '_token' => 'invalid'])
            ->assertStatus(419);
    }

    public function test_post_with_valid_token_passes_csrf_check(): void
    {
        $this->get($this->adminUrl('login'));

        $this->post($this->adminUrl('login'), ['_token' => session()->token(), 'email' => '', 'password' => ''])
            ->assertSessionHasErrors(['email', 'password']);
    }

    public function test_login_form_contains_token_and_no_xsrf_cookie_is_set(): void
    {
        $response = $this->get($this->adminUrl('login'));

        $response->assertSee('name="_token"', false);
        $names = array_map(fn ($cookie) => $cookie->getName(), $response->headers->getCookies());
        $this->assertNotContains('XSRF-TOKEN', $names);
    }
}
