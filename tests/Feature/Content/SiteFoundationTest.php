<?php

namespace Tests\Feature\Content;

use App\Contracts\QualityCheck;
use App\Enums\NavigationMenu;
use App\Enums\PublicationStatus;
use App\Enums\QualitySeverity;
use App\Exceptions\DomainRuleViolation;
use App\Models\Department;
use App\Models\ExternalResource;
use App\Models\Location;
use App\Models\NavigationItem;
use App\Models\Page;
use App\Models\SiteSettings;
use App\Services\Content\ContentUsage;
use App\Services\Content\RevisionService;
use App\Services\Navigation\NavigationManager;
use App\Services\Quality\QualityChecks;
use App\Services\Routing\RouteManager;
use App\Services\Seo\Sitemap;
use App\Support\Content\QualityIssue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesContent;
use Tests\TestCase;

class SiteFoundationTest extends TestCase
{
    use CreatesContent,RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake((string) config('uploads.disk'));
    }

    private function item(array $extra = []): NavigationItem
    {
        return NavigationItem::create(['menu' => 'main', 'label' => 'Menüpunkt', 'url' => 'https://example.test', 'is_active' => true, ...$extra]);
    }

    public function test_settings_are_authorized_validated_singleton_and_revisioned(): void
    {
        $admin = $this->createUser();
        $reader = $this->userWithPermissions(['site-settings.view']);
        $this->actingAsAdmin($reader)->post($this->adminUrl('einstellungen'), ['municipality_name' => 'Testgemeinde'])->assertForbidden();
        $this->actingAsAdmin($admin)->post($this->adminUrl('einstellungen'), [])->assertSessionHasErrors('municipality_name');
        $this->actingAsAdmin($admin)->post($this->adminUrl('einstellungen'), ['municipality_name' => 'Testgemeinde', 'default_meta_description' => 'Informationen'])->assertSessionHasNoErrors();
        $settings = SiteSettings::query()->firstOrFail();
        $this->assertSame(1, $settings->getKey());
        $this->assertSame(1, $settings->revisions()->count());
        $this->actingAsAdmin($admin)->post($this->adminUrl('einstellungen'), ['municipality_name' => 'Zweite'])->assertForbidden();
        $this->actingAsAdmin($reader)->put($this->adminUrl('einstellungen/1'), ['municipality_name' => 'X'])->assertForbidden();
        $this->actingAsAdmin($admin)->put($this->adminUrl('einstellungen/1'), ['municipality_name' => 'Neue Testgemeinde'])->assertSessionHasNoErrors();
        $this->assertSame(2, $settings->revisions()->count());
        $this->assertDatabaseHas('audit_events', ['action' => 'content.updated', 'subject_type' => 'site-settings']);
        $this->get('/')->assertSee('Neue Testgemeinde');
        $this->actingAsAdmin($admin)->delete($this->adminUrl('einstellungen/1'))->assertForbidden();
    }

    public function test_settings_reference_contacts_addresses_and_opening_hours_without_copies(): void
    {
        $location = Location::create(['name' => 'Testort', 'type' => 'verwaltung', 'street' => 'Teststraße 1', 'opening_hours' => 'Mo 9–12']);
        $department = Department::create(['name' => 'Testamt', 'phone' => '123']);
        SiteSettings::create(['municipality_name' => 'Testgemeinde', 'town_hall_location_id' => $location->id, 'central_department_id' => $department->id]);
        $department->update(['phone' => '456']);
        $settings = SiteSettings::query()->firstOrFail();
        $this->assertSame('456', $settings->centralDepartment->phone);
        $this->assertSame('Mo 9–12', $settings->townHall->getAttribute('opening_hours'));
    }

    public function test_seo_metadata_and_canonical_are_escaped_and_slashless(): void
    {
        $p = $this->page(['title' => 'Testseite']);
        $p->forceFill(['seo_title' => 'Seitentitel', 'meta_description' => 'Beschreibung <script>', 'seo_noindex' => true])->save();
        app(RouteManager::class)->assign($p, '/testseite/');
        $r = $this->get('/testseite')->assertOk();
        $r->assertSee('<title>Seitentitel – ', false)->assertSee('content="Beschreibung &lt;script&gt;"', false)->assertSee('rel="canonical" href="http://localhost/testseite"', false)->assertSee('property="og:type"', false);
        $this->assertSame([], $r->headers->getCookies());
    }

    public function test_individual_noindex_applies_in_production_to_html_and_download_headers(): void
    {
        $this->app['env'] = 'production';
        config(['site.public_indexing' => true]);
        $p = $this->page();
        $p->forceFill(['seo_noindex' => true])->save();
        app(RouteManager::class)->assign($p, '/noindex');
        $this->get('/noindex')->assertHeader('X-Robots-Tag', 'noindex, follow')->assertSee('content="noindex, follow"', false);
        $d = $this->document();
        $d->forceFill(['seo_noindex' => true])->save();
        $this->get($d->downloadPath())->assertHeader('X-Robots-Tag', 'noindex, follow');
    }

    public function test_sitemap_excludes_drafts_scheduled_private_and_noncanonical_routes(): void
    {
        $routes = app(RouteManager::class);
        $p = $this->page();
        $routes->assign($p, '/alt');
        $routes->assign($p, '/kanonisch/');
        $draft = $this->page([], PublicationStatus::Draft);
        $routes->assign($draft, '/entwurf');
        $scheduled = $this->page();
        $scheduled->forceFill(['publish_at' => now()->addDay()])->save();
        $routes->assign($scheduled, '/geplant');
        $hidden = $this->page();
        $hidden->forceFill(['seo_noindex' => true])->save();
        $routes->assign($hidden, '/nicht-indexieren');
        $r = $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8')->assertSee('http://localhost/kanonisch', false)
            ->assertDontSee('/alt</loc>', false)->assertDontSee('/entwurf')->assertDontSee('/geplant')->assertDontSee('/nicht-indexieren')->assertDontSee('/verwaltung')->assertDontSee('/freigaben')->assertDontSee('/kontakt');
        $this->assertSame([], $r->headers->getCookies());
        $xml = simplexml_load_string($r->getContent());
        $this->assertNotFalse($xml);
        foreach ($xml->url as $entry) {
            $path = parse_url((string) $entry->loc, PHP_URL_PATH);
            $this->assertTrue($path === '/' || ! str_ends_with($path, '/'));
        }
    }

    public function test_sitemap_preserves_public_archives_and_excludes_expired_pages(): void
    {
        $a = $this->article(['title' => 'Archiv'], PublicationStatus::Published, now()->subDays(2), now()->subDay());
        app(RouteManager::class)->assign($a, '/archiv');
        $p = $this->page();
        $p->forceFill(['expires_at' => now()->subHour()])->save();
        app(RouteManager::class)->assign($p, '/abgelaufen');
        $d = $this->document();
        $d->forceFill(['expires_at' => now()->subHour()])->save();
        $this->get('/sitemap.xml')->assertSee('/archiv')->assertSee($d->downloadPath())->assertDontSee('/abgelaufen');
        $this->get($d->downloadPath())->assertOk();
        $this->get('/abgelaufen')->assertNotFound();
    }

    public function test_sitemap_reflects_scheduled_activation_without_rebuild_and_rejects_invalid_part(): void
    {
        $p = $this->page();
        $p->forceFill(['publish_at' => now()->addHour()])->save();
        app(RouteManager::class)->assign($p, '/spaeter');
        $this->get('/sitemap.xml')->assertDontSee('/spaeter');
        $this->travel(61)->minutes();
        $this->get('/sitemap.xml')->assertSee('/spaeter');
        $this->get('/sitemap/2.xml')->assertNotFound();
    }

    public function test_reserved_functional_paths_cannot_be_assigned_to_content(): void
    {
        $p = $this->page();
        foreach (['/kontakt', '/sitemap.xml', '/sitemap/1.xml', '/medien/1'] as $path) {
            try {
                app(RouteManager::class)->assign($p, $path);
                $this->fail('Reserved route accepted');
            } catch (DomainRuleViolation) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_navigation_cycles_and_cross_menu_parent_are_rejected_in_admin(): void
    {
        $admin = $this->createUser();
        $a = $this->item();
        $b = $this->item(['parent_id' => $a->id]);
        $this->actingAsAdmin($admin)->put($this->adminUrl('navigation/'.$a->id), ['menu' => 'main', 'label' => 'A', 'url' => 'https://example.test', 'parent_id' => $b->id])->assertSessionHasErrors('parent_id');
        $this->actingAsAdmin($admin)->put($this->adminUrl('navigation/'.$a->id), ['menu' => 'main', 'label' => 'A', 'url' => 'https://example.test', 'parent_id' => $a->id])->assertSessionHasErrors('parent_id');
        $footer = $this->item(['menu' => 'footer']);
        $this->actingAsAdmin($admin)->put($this->adminUrl('navigation/'.$b->id), ['menu' => 'main', 'label' => 'B', 'url' => 'https://example.test', 'parent_id' => $footer->id])->assertSessionHasErrors('parent_id');
    }

    public function test_navigation_cycle_is_guarded_beyond_previous_twenty_hop_limit(): void
    {
        $first = $this->item();
        $last = $first;
        for ($i = 0; $i < 22; $i++) {
            $last = $this->item(['parent_id' => $last->id]);
        }
        $this->expectException(DomainRuleViolation::class);
        $first->update(['parent_id' => $last->id]);
    }

    public function test_moving_navigation_keeps_url_and_creates_revision_and_audit(): void
    {
        $p = $this->page();
        $route = app(RouteManager::class)->assign($p, '/legacy-pfad');
        $a = $this->item();
        $b = $this->item(['url' => null, 'public_route_id' => $route->id]);
        app(NavigationManager::class)->move($b, $a, 15);
        $this->assertSame('/legacy-pfad', $p->fresh()->publicPath());
        $this->assertSame('/legacy-pfad', $b->href());
        $this->assertSame(1, $b->revisions()->count());
        $this->assertDatabaseHas('audit_events', ['action' => 'navigation.moved']);
    }

    public function test_managed_external_navigation_and_safe_ordering(): void
    {
        $link = ExternalResource::create(['title' => 'Portal', 'url' => 'https://example.test/portal', 'type' => 'portal']);
        $link->forceFill(['status' => 'published', 'publish_at' => now()->subDay()])->save();
        $a = $this->item(['url' => null, 'external_resource_id' => $link->id, 'sort_order' => 20]);
        $b = $this->item(['sort_order' => 10]);
        $tree = app(NavigationManager::class)->tree(NavigationMenu::Main);
        $this->assertSame($b->id, $tree[0]->item->id);
        $this->assertSame($link->url, $a->href());
        $this->assertContains('Navigationsziel', array_column(app(ContentUsage::class)->of($link), 'context'));
        $link->delete();
        $this->assertNull($a->href());
        $this->assertCount(1, app(NavigationManager::class)->tree(NavigationMenu::Main));
    }

    public function test_inactive_parent_hides_subtree_and_parent_delete_is_guarded(): void
    {
        $a = $this->item(['is_active' => false]);
        $b = $this->item(['parent_id' => $a->id]);
        $this->assertSame([], app(NavigationManager::class)->tree(NavigationMenu::Main));
        $this->expectException(DomainRuleViolation::class);
        $a->delete();
    }

    public function test_unavailable_internal_navigation_target_is_not_exposed(): void
    {
        $p = $this->page([], PublicationStatus::Draft);
        $route = app(RouteManager::class)->assign($p, '/entwurf');
        $item = $this->item(['url' => null, 'public_route_id' => $route->id]);
        $this->assertNull($item->href());
        $this->assertSame([], app(NavigationManager::class)->tree(NavigationMenu::Main));
    }

    public function test_quality_check_severity_and_type_specific_extension(): void
    {
        $p = $this->page(['title' => '', 'body' => "#### Überschrift\n[Hier](https://example.test)\n<iframe src='x'></iframe>"]);
        $issues = app(QualityChecks::class)->inspect($p);
        $codes = array_column($issues, 'code');
        foreach (['title.missing', 'route.missing', 'seo.description', 'headings.skipped', 'links.label', 'privacy.embed'] as $code) {
            $this->assertContains($code, $codes);
        }
        $byCode = array_column($issues, null, 'code');
        $this->assertSame(QualitySeverity::Error, $byCode['title.missing']->severity);
        $this->assertSame(QualitySeverity::Recommendation, $byCode['seo.description']->severity);
        $this->assertSame(QualitySeverity::Warning, $byCode['headings.skipped']->severity);
        $checks = app(QualityChecks::class);
        $checks->add(new class implements QualityCheck
        {
            public function supports(Model $m): bool
            {
                return $m instanceof Page;
            }

            public function inspect(Model $m): array
            {
                return [new QualityIssue('custom', QualitySeverity::Recommendation, 'Testhinweis')];
            }
        });
        $this->assertContains('custom', array_column($checks->inspect($p), 'code'));
    }

    public function test_unchecked_documents_warn_without_blocking_publication(): void
    {
        $d = $this->document();
        $this->actingAsAdmin($this->createUser())->post($this->adminUrl('seiten'), ['title' => 'Dokumentseite', 'status' => 'published'])->assertSessionHasNoErrors();
        $p = Page::query()->firstOrFail();
        $p->documents()->attach($d->id, ['slot' => 'downloads']);
        $issues = app(QualityChecks::class)->inspect($p);
        $byCode = array_column($issues, null, 'code');
        $this->assertSame(QualitySeverity::Warning, $byCode['document.accessibility']->severity);
        app(QualityChecks::class)->enforcePublicAssets($p);
        $this->assertTrue($p->isPubliclyReachable());
    }

    public function test_seo_fields_and_media_relations_are_in_revision_snapshots(): void
    {
        $p = $this->page();
        $p->forceFill(['seo_title' => 'Titel', 'meta_description' => 'Meta', 'seo_noindex' => true])->save();
        $snapshot = app(RevisionService::class)->snapshot($p);
        $this->assertSame('Meta', $snapshot['attributes']['meta_description']);
        $this->assertArrayHasKey('media', $snapshot['relations']);
    }

    public function test_settings_revision_can_be_restored_and_legal_metadata_is_not_hardcoded(): void
    {
        $admin = $this->createUser();
        $this->actingAsAdmin($admin)->post($this->adminUrl('einstellungen'), ['municipality_name' => 'Testgemeinde', 'default_meta_description' => 'Standardbeschreibung'])->assertSessionHasNoErrors();
        $this->actingAsAdmin($admin)->put($this->adminUrl('einstellungen/1'), ['municipality_name' => 'Neuer Name'])->assertSessionHasNoErrors();
        $this->actingAsAdmin($admin)->post($this->adminUrl('einstellungen/1/versionen/1/wiederherstellen'))->assertSessionHasNoErrors();
        $this->get('/')->assertSee('Testgemeinde')->assertSee('content="Standardbeschreibung"', false);
    }

    public function test_navigation_requires_one_target_and_bounded_order_without_exceptions(): void
    {
        $admin = $this->createUser();
        foreach ([['menu' => 'invalid', 'label' => 'X', 'url' => 'https://example.test'], ['menu' => 'main', 'label' => 'X'], ['menu' => 'main', 'label' => 'X', 'url' => 'https://example.test', 'sort_order' => 70000]] as $payload) {
            $this->actingAsAdmin($admin)->post($this->adminUrl('navigation'), $payload)->assertSessionHasErrors();
        }
    }

    public function test_sitemap_index_splits_large_collections_and_escapes_urls(): void
    {
        $this->mock(Sitemap::class)->shouldReceive('entries')->andReturn(array_fill(0, 10001, ['url' => 'http://localhost/path&value', 'modified' => null]));
        $this->get('/sitemap.xml')->assertOk()->assertSee('<sitemapindex', false)->assertSee('/sitemap/2.xml');
        $r = $this->get('/sitemap/2.xml')->assertOk()->assertSee('path&amp;value', false);
        $xml = simplexml_load_string($r->getContent());
        $this->assertCount(1, $xml->url);
    }

    public function test_homepage_seo_defaults_do_not_override_contact_page_titles_or_canonical(): void
    {
        SiteSettings::create(['municipality_name' => 'Testgemeinde', 'default_seo_title' => 'Startseite']);
        $this->get('/')->assertSee('<title>Startseite – Testgemeinde</title>', false);
        $this->get('/kontakt')->assertSee('<title>Kontakt – Testgemeinde</title>', false)->assertSee('property="og:url" content="http://localhost/kontakt"', false);
    }
}
