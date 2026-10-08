<?php

namespace Tests\Feature\Content;

use App\Enums\PublicationStatus;
use App\Exceptions\DomainRuleViolation;
use App\Models\NavigationItem;
use App\Models\PublicRoute;
use App\Models\Redirect;
use App\Services\Routing\RedirectManager;
use App\Services\Routing\RouteManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesContent;
use Tests\TestCase;

class RoutingTest extends TestCase
{
    use CreatesContent, RefreshDatabase;

    private function routes(): RouteManager
    {
        return app(RouteManager::class);
    }

    private function redirects(): RedirectManager
    {
        return app(RedirectManager::class);
    }

    private function redirect(string $source, ?string $destination, int $status = 301): Redirect
    {
        return $this->redirects()->save(['source_path' => $source, 'destination' => $destination, 'status_code' => $status]);
    }

    public function test_canonical_paths_are_slashless_and_legacy_slash_urls_redirect_once(): void
    {
        $page = $this->page(['title' => 'Veranstaltungskalender']);
        // Entered with the legacy slash, stored canonically without it.
        $this->routes()->assign($page, '/veranstaltungskalender/');
        $this->assertSame('/veranstaltungskalender', $page->publicPath());

        $response = $this->rawGet('/veranstaltungskalender');
        $response->assertOk()->assertSee('<h1>Veranstaltungskalender</h1>', false)
            ->assertSee('<link rel="canonical" href="http://localhost/veranstaltungskalender">', false);
        $this->assertSame([], $response->headers->getCookies(), 'Public content pages stay cookie-free.');

        // Legacy slash URL and case variants: one 301 to the slashless canonical URL, query preserved.
        $this->rawGet('/veranstaltungskalender/?monat=7')->assertStatus(301)->assertRedirect('http://localhost/veranstaltungskalender?monat=7');
        $this->rawGet('/Veranstaltungskalender/')->assertStatus(301)->assertRedirect('http://localhost/veranstaltungskalender');
    }

    public function test_legacy_redirect_with_trailing_slash_is_a_single_hop(): void
    {
        $page = $this->page(['title' => 'Kalender']);
        $this->routes()->assign($page, '/veranstaltungen');
        $redirect = $this->redirect('/veranstaltungskalender/', '/veranstaltungen/');

        $this->assertSame('/veranstaltungskalender', $redirect->source_path);
        $this->assertSame('/veranstaltungen', $redirect->destination);
        $this->rawGet('/veranstaltungskalender/?seite=2')->assertStatus(301)->assertRedirect('http://localhost/veranstaltungen?seite=2');
        $this->rawGet('/veranstaltungen')->assertOk();
    }

    public function test_host_and_https_normalisation_go_straight_to_the_final_target(): void
    {
        $this->app['env'] = 'production';
        config([
            'app.url' => 'https://www.gemeinde-merching.de',
            'security.force_https' => true,
            'security.trusted_hosts' => ['www.gemeinde-merching.de'],
            'security.redirect_hosts' => ['gemeinde-merching.de'],
        ]);
        $page = $this->page(['title' => 'Kalender']);
        $this->routes()->assign($page, '/veranstaltungen');
        $this->redirect('/veranstaltungskalender', '/veranstaltungen');

        // Old host + http + legacy path + slash = still one hop.
        $this->rawGet('http://gemeinde-merching.de/veranstaltungskalender/?a=1')
            ->assertStatus(301)->assertRedirect('https://www.gemeinde-merching.de/veranstaltungen?a=1');
        $this->rawGet('http://www.gemeinde-merching.de/Veranstaltungen/')
            ->assertStatus(301)->assertRedirect('https://www.gemeinde-merching.de/veranstaltungen');
        $this->rawGet('http://gemeinde-merching.de/')
            ->assertStatus(301)->assertRedirect('https://www.gemeinde-merching.de/');
    }

    public function test_explicit_routes_are_slashless_too_and_root_is_unchanged(): void
    {
        $this->rawGet('/verwaltung/login/')->assertStatus(301)->assertRedirect('http://localhost/verwaltung/login');
        $this->rawGet('/robots.txt/')->assertStatus(301)->assertRedirect('http://localhost/robots.txt');
        $this->rawGet('/')->assertOk()->assertSee('<link rel="canonical" href="http://localhost/">', false);
    }

    public function test_stored_paths_never_have_a_trailing_slash(): void
    {
        foreach (['/a/', '/b//', '/c/d/'] as $i => $path) {
            try {
                $this->routes()->assign($this->page(), $path);
            } catch (DomainRuleViolation) {
            }
        }
        $this->redirect('/legacy/', '/ziel/');

        foreach (PublicRoute::pluck('path')->merge(Redirect::pluck('source_path'))->merge(Redirect::pluck('destination')) as $path) {
            $this->assertStringEndsNotWith('/', (string) $path);
        }
    }

    public function test_internal_document_links_use_the_slashless_canonical_form(): void
    {
        $page = $this->page(['title' => 'Haushalt']);
        $this->routes()->assign($page, '/haushalt/');
        $document = $this->document(['title' => 'Plan 2026']);
        $this->routes()->assign($document, '/dokumente/haushaltsplan-2026.pdf/');
        $page->documents()->attach($document->id, ['slot' => 'downloads', 'sort_order' => 0]);

        $this->rawGet('/haushalt')->assertOk()->assertSee('href="/dokumente/haushaltsplan-2026.pdf"', false);
    }

    public function test_paths_are_unique_across_records_and_variants(): void
    {
        $this->routes()->assign($this->page(), '/rathaus/aemter');

        foreach (['/rathaus/aemter', '/rathaus/aemter/', '/Rathaus/Aemter'] as $path) {
            try {
                $this->routes()->assign($this->page(), $path);
                $this->fail("Duplicate path {$path} accepted");
            } catch (DomainRuleViolation $e) {
                $this->assertSame('public_path', $e->field);
            }
        }

        $this->assertSame(1, PublicRoute::count());
    }

    public function test_umlauts_are_distinct_from_plain_letters(): void
    {
        $this->routes()->assign($this->page(), '/bürger');
        $this->routes()->assign($this->page(), '/burger');

        $this->assertSame(2, PublicRoute::count());
        $this->get('/b%C3%BCrger')->assertOk();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidPaths(): array
    {
        return [
            'no leading slash' => ['aktuelles'],
            'query' => ['/a?b=1'],
            'fragment' => ['/a#b'],
            'dot segment' => ['/a/../verwaltung'],
            'empty segment' => ['/a//b'],
            'whitespace' => ['/a b'],
            'backend' => ['/verwaltung/dashboard'],
            'robots' => ['/robots.txt'],
            'build assets' => ['/build/x.js'],
            'home' => ['/'],
        ];
    }

    #[DataProvider('invalidPaths')]
    public function test_invalid_or_reserved_paths_are_rejected(string $path): void
    {
        $this->expectException(DomainRuleViolation::class);

        $this->routes()->assign($this->page(), $path);
    }

    public function test_changing_a_path_keeps_the_old_url_working_without_chains(): void
    {
        $page = $this->page(['title' => 'Bürgerbüro']);
        $canonical = $this->routes()->assign($page, '/buergerbuero');
        $this->routes()->assign($page, '/rathaus/buergerbuero');
        $this->routes()->assign($page, '/service/buergerbuero');

        // The canonical row is stable (navigation keeps working) …
        $this->assertSame($canonical->id, $page->canonicalRoute()->first()?->id);
        $this->assertSame('/service/buergerbuero', $page->publicPath());

        // … and every former path redirects in ONE hop to the current one.
        $this->get('/buergerbuero')->assertStatus(301)->assertRedirect('http://localhost/service/buergerbuero');
        $this->get('/rathaus/buergerbuero')->assertStatus(301)->assertRedirect('http://localhost/service/buergerbuero');
        $this->get('/service/buergerbuero')->assertOk();

        // Going back to a former path re-uses it.
        $this->routes()->assign($page, '/buergerbuero');
        $this->get('/buergerbuero')->assertOk();
        $this->assertSame(3, $page->publicRoutes()->count());
        $this->assertDatabaseHas('audit_events', ['action' => 'route.changed', 'subject_id' => $page->id]);
    }

    public function test_navigation_is_independent_of_the_url(): void
    {
        $page = $this->page(['title' => 'Haushalt']);
        $route = $this->routes()->assign($page, '/haushalt');
        $item = NavigationItem::create(['menu' => 'main', 'label' => 'Haushalt', 'public_route_id' => $route->id, 'is_active' => true]);

        $this->routes()->assign($page, '/rathaus/finanzen/haushalt');

        $this->assertSame('/rathaus/finanzen/haushalt', $item->fresh()?->href());
    }

    public function test_unpublished_or_deleted_content_is_not_served(): void
    {
        $draft = $this->page(['title' => 'Entwurf'], PublicationStatus::Draft);
        $this->routes()->assign($draft, '/entwurf');
        $deleted = $this->page(['title' => 'Gelöscht']);
        $this->routes()->assign($deleted, '/geloescht');
        $deleted->delete();

        $this->get('/entwurf')->assertNotFound()->assertDontSee('Entwurf</h1>', false);
        $this->get('/geloescht')->assertNotFound();
    }

    public function test_redirect_sources_are_unique(): void
    {
        $this->redirect('/alt', '/neu');

        $this->expectException(DomainRuleViolation::class);
        $this->redirect('/ALT/', '/anders'); // same key: "/alt"
    }

    public function test_redirect_loops_are_rejected(): void
    {
        $this->redirect('/a', '/b');

        try {
            $this->redirect('/b', '/a');
            $this->fail('Loop accepted');
        } catch (DomainRuleViolation $e) {
            $this->assertStringContainsString('Schleife', $e->getMessage());
        }

        $this->expectException(DomainRuleViolation::class);
        $this->redirect('/c', '/c/');
    }

    public function test_redirect_to_a_redirect_is_rejected_and_incoming_redirects_are_flattened(): void
    {
        $this->redirect('/a', '/b');

        try {
            $this->redirect('/x', '/a');
            $this->fail('Chain accepted');
        } catch (DomainRuleViolation $e) {
            $this->assertStringContainsString('/b', $e->getMessage(), 'Editor is told the final target.');
        }

        // /b now moves to /c: /a must point directly to /c.
        $this->redirect('/b', '/c');
        $this->assertSame('/c', Redirect::query()->where('source_key', '/a')->value('destination'));
        $this->get('/a')->assertRedirect('http://localhost/c');
    }

    public function test_redirect_cannot_shadow_active_content_and_vice_versa(): void
    {
        $page = $this->page();
        $this->routes()->assign($page, '/inhalt');

        try {
            $this->redirect('/inhalt/', '/woanders');
            $this->fail('Redirect over content accepted');
        } catch (DomainRuleViolation $e) {
            $this->assertSame('source_path', $e->field);
        }

        $this->redirect('/legacy', '/inhalt');
        $this->expectException(DomainRuleViolation::class);
        $this->routes()->assign($this->page(), '/legacy');
    }

    public function test_redirect_to_former_content_path_suggests_current_path(): void
    {
        $page = $this->page();
        $this->routes()->assign($page, '/vorher');
        $this->routes()->assign($page, '/nachher');

        $this->expectExceptionMessage('/nachher');
        $this->redirect('/ganz-alt', '/vorher');
    }

    public function test_content_routes_take_precedence_over_redirects(): void
    {
        $page = $this->page(['title' => 'Vorrang']);
        $this->routes()->assign($page, '/vorrang');
        // Legacy data inserted bypassing validation.
        Redirect::query()->insert(['source_path' => '/vorrang', 'source_key' => '/vorrang', 'destination' => '/anderswo', 'status_code' => 301, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);

        $this->get('/vorrang')->assertOk()->assertSee('Vorrang');
    }

    public function test_redirect_statuses_and_external_targets(): void
    {
        $this->redirect('/entfernt', null, 410);
        $this->redirect('/extern', 'https://www.bayernportal.de/');
        $this->redirect('/temporaer', '/aktuelles', 302);

        $this->get('/entfernt')->assertStatus(410);
        $this->get('/extern')->assertStatus(301)->assertRedirect('https://www.bayernportal.de/');
        $this->get('/temporaer')->assertStatus(302);

        $this->expectException(DomainRuleViolation::class);
        $this->redirect('/boese', 'javascript:alert(1)');
    }

    public function test_inactive_redirect_is_ignored(): void
    {
        $this->redirects()->save(['source_path' => '/inaktiv', 'destination' => '/ziel', 'status_code' => 301, 'is_active' => false]);

        $this->get('/inaktiv')->assertNotFound();
    }

    public function test_suggested_paths_avoid_collisions(): void
    {
        $first = $this->page(['title' => 'Kindergarten']);
        $this->routes()->assign($first, $this->routes()->suggestPath($first));
        $second = $this->page(['title' => 'Kindergarten']);

        $this->assertSame('/kindergarten-2', $this->routes()->suggestPath($second));
    }

    public function test_unknown_paths_are_404_for_every_method(): void
    {
        $this->get('/gibt-es-nicht')->assertNotFound();
        $this->post('/gibt-es-nicht')->assertNotFound();
    }
}
