<?php

namespace Tests\Feature\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CanonicalHostTest extends TestCase
{
    use RefreshDatabase;

    private const CANONICAL = 'https://www.gemeinde-merching.de';

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['env'] = 'production';
        config([
            'app.url' => self::CANONICAL,
            'security.force_https' => true,
            'security.trusted_hosts' => ['www.gemeinde-merching.de'],
            'security.redirect_hosts' => ['gemeinde-merching.de'],
        ]);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function redirects(): array
    {
        return [
            'non-www over https' => ['https://gemeinde-merching.de/', '/'],
            'non-www over http, single hop' => ['http://gemeinde-merching.de/rathaus/buergerservice', '/rathaus/buergerservice'],
            'www over http' => ['http://www.gemeinde-merching.de/aktuelles?seite=2', '/aktuelles?seite=2'],
            'legacy path with encoding and query kept' => ['https://gemeinde-merching.de/index.php/B%C3%BCrger/Formulare.html?id=17&x=a%20b', '/index.php/B%C3%BCrger/Formulare.html?id=17&x=a%20b'],
            'upper-case alias host' => ['https://GEMEINDE-MERCHING.DE/kontakt', '/kontakt'],
        ];
    }

    #[DataProvider('redirects')]
    public function test_requests_are_redirected_to_canonical_url_preserving_path(string $requested, string $expectedPath): void
    {
        $response = $this->get($requested);

        $response->assertStatus(301);
        $this->assertSame(self::CANONICAL.$expectedPath, $response->headers->get('Location'));
        $this->assertSame([], $response->headers->getCookies());
    }

    public function test_canonical_url_is_served_directly(): void
    {
        $this->get(self::CANONICAL.'/')->assertOk()->assertHeader('Strict-Transport-Security');
    }

    public function test_non_get_requests_keep_their_method(): void
    {
        $this->post('https://gemeinde-merching.de/verwaltung/login')
            ->assertStatus(308)
            ->assertHeader('Location', self::CANONICAL.'/verwaltung/login');
    }

    public function test_redirect_responses_carry_security_headers(): void
    {
        $this->get('https://gemeinde-merching.de/')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Strict-Transport-Security');
    }

    public function test_unknown_hosts_are_rejected_not_redirected(): void
    {
        $this->get('https://evil.example/')->assertStatus(400);
        $this->get('https://sub.gemeinde-merching.de/')->assertStatus(400);
    }

    public function test_alias_host_is_rejected_when_not_configured(): void
    {
        config(['security.redirect_hosts' => []]);

        $this->get('https://gemeinde-merching.de/')->assertStatus(400);
    }
}
