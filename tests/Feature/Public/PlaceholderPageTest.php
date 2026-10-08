<?php

namespace Tests\Feature\Public;

use Tests\TestCase;

class PlaceholderPageTest extends TestCase
{
    public function test_placeholder_page_is_served(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('<html lang="de">', false)
            ->assertSee('<title>Entwicklungsumgebung – Gemeinde Merching</title>', false)
            ->assertSee('<h1>Gemeinde Merching – Entwicklungsumgebung</h1>', false);
    }

    public function test_page_has_accessible_landmarks_and_skip_link(): void
    {
        $html = $this->get('/')->getContent();

        $this->assertStringContainsString('<a class="skip-link" href="#inhalt">Zum Inhalt springen</a>', $html);
        $this->assertMatchesRegularExpression('/<main id="inhalt"[^>]*>/', $html);
        $this->assertStringContainsString('<header', $html);
        $this->assertStringContainsString('<footer', $html);
        $this->assertSame(1, substr_count($html, '<h1'));
        // Zoom must not be disabled.
        $this->assertStringNotContainsString('user-scalable=no', $html);
        $this->assertStringNotContainsString('maximum-scale', $html);
    }

    public function test_anonymous_public_request_sets_no_cookies(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $this->assertSame([], $response->headers->getCookies(), 'Public pages must not set cookies.');
        $this->assertFalse($response->headers->has('Set-Cookie'));
    }

    public function test_unknown_public_url_returns_404_without_cookies(): void
    {
        $response = $this->get('/gibt-es-nicht');

        $response->assertNotFound()->assertSee('Seite nicht gefunden');
        $this->assertSame([], $response->headers->getCookies());
    }
}
