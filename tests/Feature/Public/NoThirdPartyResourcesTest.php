<?php

namespace Tests\Feature\Public;

use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Privacy by default: rendered pages must not reference resources on other
 * hosts (fonts, scripts, styles, images, tracking pixels, iframes).
 * The browser-level check lives in tests/Browser (Playwright).
 */
class NoThirdPartyResourcesTest extends TestCase
{
    use RefreshDatabase;

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

        // Canonical/sitemap and microdata links are passive identifiers, not
        // browser-loaded assets. They deliberately identify the production site.
        $document = new DOMDocument;
        @$document->loadHTML($html);
        $dom = new DOMXPath($document);
        foreach ($dom->query('//link[@rel="canonical" or @rel="sitemap" or (not(@rel) and (@itemprop="url" or @itemprop="logo" or @itemprop="image" or @itemprop="eventStatus"))]') as $link) {
            $this->assertSame(
                $link->getAttribute('itemprop') === 'eventStatus' ? 'schema.org' : parse_url(config('seo.origin'), PHP_URL_HOST),
                parse_url($link->getAttribute('href'), PHP_URL_HOST),
            );
            $link->parentNode->removeChild($link);
        }
        $html = $document->saveHTML();

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
