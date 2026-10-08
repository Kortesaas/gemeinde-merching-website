<?php

namespace Tests\Feature\Public;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Privacy by default: rendered pages must not reference resources on other
 * hosts (fonts, scripts, styles, images, tracking pixels, iframes).
 * The browser-level check lives in tests/Browser (Playwright).
 */
class NoThirdPartyResourcesTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function pages(): array
    {
        return [
            'public placeholder' => ['/'],
            'admin login' => ['/verwaltung/login'],
            'password forgotten' => ['/verwaltung/passwort-vergessen'],
            '404 page' => ['/gibt-es-nicht'],
        ];
    }

    #[DataProvider('pages')]
    public function test_page_loads_no_external_resources(string $path): void
    {
        $html = (string) $this->get($path)->getContent();

        preg_match_all('/\b(?:src|href|action|srcset|data)\s*=\s*"([^"]+)"/i', $html, $matches);

        foreach ($matches[1] as $url) {
            if (preg_match('#^(https?:)?//#i', $url)) {
                $this->assertSame(
                    parse_url(config('app.url'), PHP_URL_HOST),
                    parse_url($url, PHP_URL_HOST),
                    "External resource referenced: {$url}",
                );
            }
        }

        foreach (['googletagmanager', 'google-analytics', 'fonts.googleapis', 'fonts.gstatic', 'facebook', 'matomo', 'bunny.net', 'cdn.'] as $needle) {
            $this->assertStringNotContainsStringIgnoringCase($needle, $html);
        }
    }
}
