<?php

namespace Tests\Feature\Public;

use App\Enums\NavigationMenu;
use App\Enums\PublicationStatus;
use App\Models\Category;
use App\Models\Event;
use App\Models\Media;
use App\Models\NavigationItem;
use App\Models\Service;
use App\Services\Content\MediaStorage;
use App\Services\Navigation\NavigationManager;
use App\Services\Routing\RedirectManager;
use App\Services\Routing\RouteManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesContent;
use Tests\TestCase;

class VisualUiTest extends TestCase
{
    use CreatesContent, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake((string) config('uploads.disk'));
    }

    public function test_home_and_configurable_empty_catalogs_are_stateless_and_do_not_invent_facts(): void
    {
        foreach (['/', '/buergerservice', '/buergerservice/a-z', '/aktuelles', '/veranstaltungen', '/bekanntmachungen', '/dokumente', '/verzeichnisse', '/suche', '/suche?q=unbekannt'] as $path) {
            $response = $this->get($path)->assertOk()->assertSee('<main', false);
            $this->assertSame([], $response->headers->getCookies());
            $response->assertDontSee('Google Maps')->assertDontSee('Max Mustermann');
        }
        $this->get('/suche?q=unbekannt')->assertSee('Keine Ergebnisse gefunden')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_suggestions_and_results_recheck_visibility_and_do_not_record_disabled_statistics(): void
    {
        config(['search.statistics_enabled' => false]);
        $page = $this->page(['title' => 'Visueller Suchtest', 'body' => 'Öffentliche Information']);
        app(RouteManager::class)->assign($page, '/visueller-suchtest');
        $this->page(['title' => 'Visueller Suchtest privat'], PublicationStatus::Draft);
        $suggestions = $this->getJson('/suche/vorschlaege?q=Visueller')->assertOk()->assertJsonCount(1, 'results')->assertJsonPath('results.0.title', $page->title);
        $this->assertSame(['title', 'url', 'type'], array_keys($suggestions->json('results.0')));
        $this->assertSame([], $suggestions->headers->getCookies());
        $this->get('/suche?q=Visueller')->assertOk()->assertSee($page->title)->assertDontSee('Suchtest privat');
        $page->forceFill(['expires_at' => now()->subMinute()])->save();
        $this->getJson('/suche/vorschlaege?q=Visueller')->assertJsonCount(0, 'results');
        $this->assertDatabaseCount('search_statistics', 0);
    }

    public function test_service_category_filter_and_az_use_real_public_relationships(): void
    {
        $category = Category::create(['context' => 'service', 'name' => 'Testkategorie', 'slug' => 'testkategorie']);
        $service = Service::create(['title' => 'Äußerst lange Testleistung', 'category_id' => $category->id]);
        $service->forceFill(['status' => PublicationStatus::Published, 'publish_at' => now()->subDay()])->save();
        app(RouteManager::class)->assign($service, '/testleistung');
        $this->get('/buergerservice?category='.$category->id)->assertOk()->assertSee($service->title)->assertSee('Testkategorie');
        $this->get('/buergerservice/a-z')->assertSee('id="letter-0"', false)->assertSee('<h2>A</h2>', false);
        $this->get('/buergerservice?category=99999')->assertDontSee($service->title);
        $service->delete();
        $this->get('/buergerservice')->assertDontSee($service->title)->assertDontSee('Testkategorie');
    }

    public function test_past_event_without_automatic_expiry_appears_in_archive_only_and_remains_reachable(): void
    {
        $event = Event::create(['title' => 'Vergangener Testtermin', 'starts_at' => now()->subDays(2), 'auto_archive' => false]);
        $event->forceFill(['status' => PublicationStatus::Published, 'publish_at' => now()->subDays(3)])->save();
        app(RouteManager::class)->assign($event, '/testtermin');
        $this->get('/veranstaltungen')->assertDontSee($event->title);
        $this->get('/veranstaltungen?archiv=1')->assertSee($event->title);
        $this->get('/testtermin')->assertOk();
        $event->forceFill(['status' => PublicationStatus::Draft])->save();
        $this->get('/veranstaltungen?archiv=1')->assertDontSee($event->title);
    }

    public function test_public_document_replacement_moves_old_version_to_archive_without_exposing_private_replacement(): void
    {
        $old = $this->document(['title' => 'Öffentliche alte Fassung']);
        $replacement = $this->document(['title' => 'Private neue Fassung', 'replaces_document_id' => $old->id], PublicationStatus::Draft);
        $this->get('/dokumente')->assertSee($old->title)->assertDontSee($replacement->title)->assertDontSee('Ersetzt durch');
        $replacement->forceFill(['status' => PublicationStatus::Published, 'publish_at' => now()->subDay()])->save();
        $this->get('/dokumente')->assertDontSee($old->title)->assertSee($replacement->title);
        $this->get('/dokumente?archiv=1')->assertSee($old->title)->assertSee('Neuere Fassung')->assertSee($replacement->title);
        $this->get($old->downloadPath())->assertOk()->assertStreamedContent(self::PDF);
    }

    public function test_managed_pages_and_redirects_take_precedence_over_catalogs(): void
    {
        $page = $this->page(['title' => 'Redaktioneller Bürgerservice', 'body' => 'Ein gepflegter Einführungstext.']);
        app(RouteManager::class)->assign($page, '/buergerservice');
        $this->get('/buergerservice')->assertOk()->assertSee($page->title)->assertSee('Ein gepflegter Einführungstext.');
        app(RouteManager::class)->assign($page, '/neuer-service-einstieg');
        $this->get('/buergerservice')->assertRedirect('/neuer-service-einstieg');
    }

    public function test_breadcrumbs_follow_visible_navigation_while_moves_preserve_urls(): void
    {
        $parent = $this->page(['title' => 'Überblick']);
        $child = $this->page(['title' => 'Unabhängige Unterseite']);
        $routes = app(RouteManager::class);
        $routes->assign($parent, '/ueberblick');
        $routes->assign($child, '/unabhaengig');
        $item = NavigationItem::create(['menu' => 'main', 'label' => 'Bereich', 'public_route_id' => $parent->canonicalRoute->id, 'is_active' => true]);
        $leaf = NavigationItem::create(['menu' => 'main', 'label' => 'Unterseite', 'parent_id' => $item->id, 'public_route_id' => $child->canonicalRoute->id, 'is_active' => true]);
        $nav = app(NavigationManager::class);
        $this->assertCount(2, $nav->trail($nav->tree(NavigationMenu::Main), '/unabhaengig'));
        $this->get('/unabhaengig')->assertSee('Brotkrümelnavigation');
        $nav->move($leaf, null, 0);
        $this->assertCount(1, $nav->trail($nav->tree(NavigationMenu::Main), '/unabhaengig'));
        $this->assertSame('/unabhaengig', $child->fresh()->publicPath());
    }

    public function test_public_image_variants_keep_owner_authorization_and_original_bytes(): void
    {
        $medium = new Media(['title' => 'Synthetisches Bild', 'alt_text' => 'Testbild']);
        app(MediaStorage::class)->attach($medium, UploadedFile::fake()->image('test.png', 1600, 900));
        $medium->forceFill(['status' => PublicationStatus::Published, 'publish_at' => now()->subDay()])->save();
        $path = '/medien/'.$medium->id.'?width=480';
        $this->get($path)->assertNotFound();
        $page = $this->page();
        $page->media()->attach($medium->id);
        $original = Storage::disk((string) config('uploads.disk'))->get($medium->file_path);
        $response = $this->get($path)->assertOk()->assertHeader('Content-Type', 'image/webp');
        $dimensions = getimagesizefromstring($response->streamedContent());
        $this->assertSame([480, 270], array_slice($dimensions, 0, 2));
        $this->assertSame([], $response->headers->getCookies());
        $this->assertSame($original, Storage::disk((string) config('uploads.disk'))->get($medium->file_path));
        $this->assertCount(1, Storage::disk((string) config('uploads.disk'))->allFiles());
        $this->getJson('/medien/'.$medium->id.'?width=9999')->assertUnprocessable();
        $page->delete();
        $this->get($path)->assertNotFound();
    }

    public function test_editorial_overview_and_dashboard_only_show_authorized_entities_and_actions(): void
    {
        $page = $this->page(['title' => 'Berechtigte Testseite']);
        $this->article(['title' => 'Geheimer Testartikel']);
        $editor = $this->userWithPermissions(['page.view']);
        $this->actingAsAdmin($editor)->get($this->adminUrl('inhalte'))->assertOk()->assertSee($page->title)->assertDontSee('Geheimer Testartikel');
        $this->actingAsAdmin($editor)->get($this->adminUrl('dashboard'))->assertOk()->assertSee($page->title)->assertDontSee('Geheimer Testartikel')->assertDontSee('Artikel anlegen')->assertDontSee('Dokument anlegen');
    }

    public function test_gone_redirect_uses_branded_error_view_without_cookies(): void
    {
        app(RedirectManager::class)->save(['source_path' => '/entfernt', 'status_code' => 410, 'is_active' => true]);
        $response = $this->get('/entfernt')->assertGone()->assertSee('Inhalt nicht mehr verfügbar')->assertDontSee('Stack trace');
        $this->assertSame([], $response->headers->getCookies());
    }

    public function test_listing_variants_redirect_directly_to_slashless_canonical_path_with_query(): void
    {
        $this->rawGet('/BUERGERSERVICE/?q=test')->assertStatus(301)->assertRedirect('/buergerservice?q=test');
        $this->rawGet('/DOKUMENTE/')->assertStatus(301)->assertRedirect('/dokumente');
        $this->assertSame(['location' => '/buergerservice', 'status' => 301], app(RouteManager::class)->finalLocation('/BUERGERSERVICE/'));
    }
}
