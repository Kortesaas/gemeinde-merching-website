<?php

namespace Tests\Feature\Content;

use App\Enums\PublicationStatus;
use App\Rules\SafeUrl;
use App\Services\Routing\RouteManager;
use App\Support\Content\SafeMarkdown;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesContent;
use Tests\TestCase;

class ContentSafetyTest extends TestCase
{
    use CreatesContent, RefreshDatabase;

    private const PAYLOAD = <<<'MD'
## Überschrift

<script>alert('xss')</script>

<img src=x onerror="alert(1)">

Text mit <a href="javascript:alert(2)" onclick="alert(4)">Klick</a> inline.

[Link](javascript:alert(3)) und [Daten](data:text/html;base64,PHNjcmlwdD4=)

![Tracking-Pixel](https://tracker.example/pixel.gif)

# Zweite H1

[Gemeinde](https://www.gemeinde-merching.de)
MD;

    public function test_markdown_rendering_neutralises_scripts_handlers_and_unsafe_links(): void
    {
        $html = (string) SafeMarkdown::toHtml(self::PAYLOAD);

        // Raw HTML is escaped to inert text; no element or attribute survives.
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringNotContainsString('src=', str_replace('&lt;img src=', '', $html));
        $this->assertDoesNotMatchRegularExpression('/<a[^>]*href="(javascript|data):/i', $html);
        $this->assertDoesNotMatchRegularExpression('/<[a-z]+[^>]*\son[a-z]+=/i', $html, 'No event-handler attributes.');
        $this->assertStringNotContainsString('tracker.example', $html, 'Images are not rendered: no external requests.');
        $this->assertStringNotContainsString('<h1>', $html, 'Only the page title is h1.');
        $this->assertStringContainsString('<h2>Überschrift</h2>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString('<a href="https://www.gemeinde-merching.de">Gemeinde</a>', $html);
    }

    public function test_public_page_never_outputs_executable_editor_content(): void
    {
        $page = $this->page(['title' => 'Test', 'body' => self::PAYLOAD]);
        app(RouteManager::class)->assign($page, '/xss-test');

        $html = (string) $this->get('/xss-test')->assertOk()->getContent();

        $this->assertStringNotContainsString('<script>alert', $html);
        $this->assertStringNotContainsString('<img', $html);
        $this->assertDoesNotMatchRegularExpression('/<[a-z]+[^>]*\son[a-z]+=/i', $html);
        $this->assertDoesNotMatchRegularExpression('/<[a-z]+[^>]*(href|src)="javascript:/i', $html);
    }

    public function test_titles_are_escaped(): void
    {
        $page = $this->page(['title' => '<script>alert(1)</script>'], PublicationStatus::Published);
        app(RouteManager::class)->assign($page, '/titel-test');

        $this->get('/titel-test')->assertOk()->assertDontSee('<script>alert(1)</script>', false)->assertSee('&lt;script&gt;', false);
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function urls(): array
    {
        return [
            'https' => ['https://www.bayernportal.de/dokumente', true],
            'http' => ['http://example.test/', true],
            'javascript' => ['javascript:alert(1)', false],
            'javascript mixed case' => ['JaVaScRiPt:alert(1)', false],
            'data' => ['data:text/html,<script>', false],
            'vbscript' => ['vbscript:msgbox', false],
            'file' => ['file:///etc/passwd', false],
            'credentials' => ['https://user:pass@example.test', false],
            'relative' => ['/aktuelles', false],
            'whitespace' => [' https://example.test', false],
            'newline' => ["https://example.test/\nSet-Cookie:x", false],
        ];
    }

    #[DataProvider('urls')]
    public function test_url_validation(string $url, bool $expected): void
    {
        $this->assertSame($expected, SafeUrl::isSafe($url));
    }

    public function test_unsafe_link_cannot_be_saved_as_external_resource(): void
    {
        $this->actingAsAdmin($this->createUser())->post($this->adminUrl('links'), ['title' => 'Böse', 'url' => 'javascript:alert(1)', 'type' => 'portal'])
            ->assertSessionHasErrors('url');
        $this->assertDatabaseCount('external_resources', 0);
    }
}
