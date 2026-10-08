<?php

namespace Tests\Feature\Content;

use App\Contracts\Routable;
use App\Enums\PublicationStatus;
use App\Exceptions\DomainRuleViolation;
use App\Models\Article;
use App\Models\Department;
use App\Models\Document;
use App\Models\LifeSituation;
use App\Models\Location;
use App\Models\Organization;
use App\Models\Person;
use App\Models\PublicRoute;
use App\Services\Routing\RouteManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesContent;
use Tests\TestCase;

class RoutePolicyTest extends TestCase
{
    use CreatesContent, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake((string) config('uploads.disk'));
    }

    public function test_directory_entries_get_no_public_route_unless_opted_in(): void
    {
        $admin = $this->createUser();

        $this->actingAsAdmin($admin)->post($this->adminUrl('aemter'), ['name' => 'Bauamt', 'public_path' => '', 'is_active' => '1'])->assertSessionHasNoErrors();
        $this->actingAsAdmin($admin)->post($this->adminUrl('orte'), ['name' => 'Bauhof', 'type' => 'einrichtung', 'is_active' => '1'])->assertSessionHasNoErrors();
        $this->actingAsAdmin($admin)->post($this->adminUrl('verzeichnis'), ['name' => 'Sportverein', 'type' => 'verein', 'is_active' => '1'])->assertSessionHasNoErrors();

        $this->assertSame(0, PublicRoute::count());
        foreach ([Department::class, Location::class, Organization::class] as $class) {
            $this->assertFalse($class::createsRouteAutomatically());
            $this->assertNull($class::query()->firstOrFail()->publicPath());
        }
    }

    public function test_opt_in_page_can_be_created_withdrawn_and_reactivated(): void
    {
        $admin = $this->createUser();
        $this->actingAsAdmin($admin)->post($this->adminUrl('orte'), ['name' => 'Mandichosee', 'type' => 'freizeit', 'is_active' => '1', 'public_path' => '/mandichosee'])
            ->assertSessionHasNoErrors();
        $location = Location::query()->firstOrFail();

        $this->get('/mandichosee')->assertOk()->assertSee('Mandichosee');

        // Clearing the path withdraws the page (404), without freeing the URL for others.
        $this->actingAsAdmin($admin)->put($this->adminUrl('orte/'.$location->id), ['name' => 'Mandichosee', 'type' => 'freizeit', 'is_active' => '1', 'public_path' => ''])
            ->assertSessionHasNoErrors();
        $this->get('/mandichosee')->assertNotFound();
        $this->assertNull($location->fresh()?->publicPath());
        $this->assertDatabaseHas('audit_events', ['action' => 'route.deactivated', 'subject_id' => $location->id]);

        $this->actingAsAdmin($admin)->put($this->adminUrl('orte/'.$location->id), ['name' => 'Mandichosee', 'type' => 'freizeit', 'is_active' => '1', 'public_path' => '/mandichosee'])
            ->assertSessionHasNoErrors();
        $this->get('/mandichosee')->assertOk();
        $this->assertSame(1, PublicRoute::count());
    }

    public function test_people_never_get_public_profile_urls(): void
    {
        $this->assertFalse(is_subclass_of(Person::class, Routable::class));
        $this->assertFalse(method_exists(Person::class, 'publicRoutes'));
    }

    public function test_documents_get_no_detail_page_but_a_download_url(): void
    {
        $this->actingAsAdmin($this->createUser())->post($this->adminUrl('dokumente'), [
            'title' => 'Satzung', 'accessibility_status' => 'not_checked',
            'status' => 'published',
            'file' => UploadedFile::fake()->createWithContent('Satzung über Gebühren.pdf', self::PDF),
        ])->assertSessionHasNoErrors();

        $document = Document::query()->firstOrFail();
        $this->assertSame(0, PublicRoute::count(), 'No automatic /dokumente/{slug} page.');
        $this->assertSame('/download/'.$document->id.'/satzung-ueber-gebuehren.pdf', $document->downloadPath());

        $response = $this->get($document->downloadPath());
        $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertSame([], $response->headers->getCookies());

        // Wrong filename segment: one redirect to the current URL.
        $this->get('/download/'.$document->id.'/alt.pdf?x=1')->assertStatus(301)
            ->assertRedirect('http://localhost/download/'.$document->id.'/satzung-ueber-gebuehren.pdf?x=1');
    }

    public function test_unpublished_documents_cannot_be_downloaded_publicly(): void
    {
        $draft = $this->document([], PublicationStatus::Draft);
        $trashed = $this->document();
        $trashed->delete();

        $this->get($draft->downloadPath())->assertNotFound();
        $this->get('/download/'.$trashed->id.'/test.pdf')->assertNotFound();
        $this->get('/download/999999/test.pdf')->assertNotFound();
    }

    public function test_migrated_document_url_takes_precedence_over_download_url(): void
    {
        $document = $this->document();
        app(RouteManager::class)->assign($document, '/wp-content/uploads/2023/05/satzung.pdf');

        $this->assertSame('/wp-content/uploads/2023/05/satzung.pdf', $document->fresh()?->downloadPath());
        $this->get('/download/'.$document->id.'/test.pdf')->assertStatus(301)->assertRedirect('http://localhost/wp-content/uploads/2023/05/satzung.pdf');
        $this->get('/wp-content/uploads/2023/05/satzung.pdf')->assertOk();
    }

    public function test_content_pages_link_documents_via_their_download_url(): void
    {
        $page = $this->page(['title' => 'Haushalt']);
        app(RouteManager::class)->assign($page, '/haushalt');
        $document = $this->document(['title' => 'Haushaltsplan']);
        $page->documents()->attach($document->id, ['slot' => 'downloads', 'sort_order' => 0]);

        $this->get('/haushalt')->assertOk()->assertSee('href="/download/'.$document->id.'/test.pdf"', false);
    }

    public function test_download_prefix_is_reserved(): void
    {
        $this->expectException(DomainRuleViolation::class);
        app(RouteManager::class)->assign($this->page(), '/download/irgendwas');
    }

    public function test_new_records_get_slashless_convention_paths(): void
    {
        $admin = $this->createUser();
        $this->actingAsAdmin($admin)->post($this->adminUrl('lebenslagen'), ['title' => 'Umzug']);
        $this->actingAsAdmin($admin)->post($this->adminUrl('artikel'), ['title' => 'Neues Feuerwehrhaus']);
        $this->actingAsAdmin($admin)->post($this->adminUrl('buergerservice'), ['title' => 'Personalausweis']);
        $this->actingAsAdmin($admin)->post($this->adminUrl('veranstaltungen'), ['title' => 'Dorffest', 'starts_at' => '2026-07-04T18:00']);

        $this->assertEqualsCanonicalizing(
            ['/buergerservice/lebenslagen/umzug', '/aktuelles/neues-feuerwehrhaus', '/buergerservice/personalausweis', '/veranstaltungen/dorffest'],
            PublicRoute::pluck('path')->all(),
        );
        $this->assertSame('/buergerservice/lebenslagen', LifeSituation::defaultPathPrefix());
    }

    public function test_migrated_urls_are_never_replaced_by_conventions(): void
    {
        $admin = $this->createUser();
        $article = $this->article(['title' => 'Alter Beitrag']);
        app(RouteManager::class)->assign($article, '/2019/05/alter-beitrag.html');

        // Saving (also with a new title and an empty path field) keeps the legacy URL.
        $this->actingAsAdmin($admin)->put($this->adminUrl('artikel/'.$article->id), ['title' => 'Neuer Titel', 'public_path' => ''])->assertSessionHasNoErrors();

        $this->assertSame('/2019/05/alter-beitrag.html', Article::query()->firstOrFail()->publicPath());
        $this->assertSame(1, PublicRoute::count());
    }
}
