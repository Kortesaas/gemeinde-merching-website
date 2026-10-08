<?php

namespace Tests\Feature\Admin;

use App\Admin\ResourceRegistry;
use App\Models\Article;
use App\Models\Category;
use App\Models\Department;
use App\Models\ExternalResource;
use App\Models\Person;
use App\Models\Service;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesContent;
use Tests\TestCase;

/**
 * End-to-end check of the functional admin CRUD for every entity type.
 */
class ContentCrudTest extends TestCase
{
    use CreatesContent, RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake((string) config('uploads.disk'));
        $this->admin = $this->createUser();
    }

    /**
     * @return array<string, array{string, string, array<string, mixed>}>
     */
    public static function resources(): array
    {
        return [
            'article' => ['article', 'artikel', ['title' => 'Neues Feuerwehrhaus', 'summary' => 'Kurz', 'body' => "## Abschnitt\n\nText", 'is_featured' => '1']],
            'event' => ['event', 'veranstaltungen', ['title' => 'Dorffest', 'starts_at' => '2026-07-04T18:00', 'ends_at' => '2026-07-04T23:00', 'venue' => 'Festplatz', 'auto_archive' => '1']],
            'notice' => ['notice', 'bekanntmachungen', ['title' => 'Bekanntmachung Bebauungsplan', 'published_on' => '2026-05-01']],
            'external-resource' => ['external-resource', 'links', ['title' => 'Bürgerserviceportal', 'url' => 'https://www.buergerserviceportal.de', 'type' => 'portal']],
            'service' => ['service', 'buergerservice', ['title' => 'Personalausweis beantragen', 'aliases' => "Perso\nAusweis", 'summary' => 'Antrag im Bürgerbüro']],
            'life-situation' => ['life-situation', 'lebenslagen', ['title' => 'Umzug']],
            'page' => ['page', 'seiten', ['title' => 'Haushaltspläne', 'public_path' => '/rathaus/haushalt']],
            'site-alert' => ['site-alert', 'hinweise', ['title' => 'Sperrung', 'body' => 'Hauptstraße gesperrt', 'severity' => 'warning']],
            'person' => ['person', 'personen', ['last_name' => 'Muster', 'first_name' => 'Max', 'phone' => '08233 1234-0', 'email' => 'max.muster@example.test', 'is_active' => '1']],
            'department' => ['department', 'aemter', ['name' => 'Bauamt', 'phone' => '08233 1234-10', 'email' => 'bauamt@example.test', 'is_active' => '1']],
            'location' => ['location', 'orte', ['name' => 'Rathaus', 'type' => 'verwaltung', 'postal_code' => '86504', 'latitude' => '48.2441', 'is_active' => '1']],
            'organization' => ['organization', 'verzeichnis', ['name' => 'Sportverein', 'type' => 'verein', 'website' => 'https://example.test', 'links' => 'Satzung | https://example.test/satzung', 'is_active' => '1']],
            'contact-route' => ['contact-route', 'kontakt-themen', ['label' => 'Meldewesen', 'recipients' => 'meldeamt@intern.example.test', 'is_active' => '1']],
            'categories' => ['kategorien', 'kategorien', ['context' => 'article', 'name' => 'Aus Merching']],
            'tags' => ['schlagwoerter', 'schlagwoerter', ['name' => 'Feuerwehr']],
            'navigation' => ['navigation', 'navigation', ['menu' => 'main', 'label' => 'Externer Link', 'url' => 'https://example.test', 'is_active' => '1']],
            'redirect' => ['redirect', 'weiterleitungen', ['source_path' => '/veranstaltungskalender/', 'destination' => '/veranstaltungen', 'status_code' => '301', 'is_active' => '1']],
        ];
    }

    #[DataProvider('resources')]
    public function test_admin_can_list_create_and_edit(string $key, string $slug, array $payload): void
    {
        $this->actingAsAdmin($this->admin)->get($this->adminUrl($slug))->assertOk()->assertSee('<h1>', false);
        $this->actingAsAdmin($this->admin)->get($this->adminUrl($slug.'/neu'))->assertOk();

        $response = $this->actingAsAdmin($this->admin)->post($this->adminUrl($slug), $payload);
        $response->assertSessionHasNoErrors()->assertRedirect();

        $resource = ResourceRegistry::get($key);
        $model = $resource->query()->latest('id')->firstOrFail();
        $this->actingAsAdmin($this->admin)->get($this->adminUrl($slug.'/'.$model->getKey()))->assertOk()->assertSee($model->displayTitle());

        $this->actingAsAdmin($this->admin)->get($this->adminUrl($slug))->assertOk()->assertSee($model->displayTitle());
        $this->assertDatabaseHas('audit_events', ['action' => $key === 'redirect' ? 'redirect.created' : 'content.created', 'user_id' => $this->admin->id]);
    }

    public function test_document_upload_end_to_end(): void
    {
        $this->actingAsAdmin($this->admin)->post($this->adminUrl('dokumente'), [
            'title' => 'Haushaltsplan 2026',
            'year' => '2026',
            'accessibility_status' => 'not_checked',
            'file' => UploadedFile::fake()->createWithContent('Haushalt 2026.pdf', self::PDF),
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->actingAsAdmin($this->admin)->get($this->adminUrl('dokumente'))->assertOk()->assertSee('Haushaltsplan 2026');
    }

    public function test_relations_are_saved_and_reused(): void
    {
        $category = Category::create(['context' => 'service', 'name' => 'Bürgerbüro', 'slug' => 'buergerbuero']);
        $department = Department::create(['name' => 'Einwohnermeldeamt', 'phone' => '08233 1234-20']);
        $person = Person::create(['last_name' => 'Beispiel']);
        $online = ExternalResource::create(['title' => 'Online-Antrag', 'url' => 'https://example.test/antrag', 'type' => 'online_service']);

        $this->actingAsAdmin($this->admin)->post($this->adminUrl('buergerservice'), [
            'title' => 'Reisepass',
            'category_id' => $category->id,
            'online_service_resource_id' => $online->id,
            'departments__present' => '1',
            'departments' => [$department->id],
            'contacts__present' => '1',
            'contacts' => [$person->id],
            'contacts_order' => [$person->id => 5],
        ])->assertSessionHasNoErrors();

        $service = Service::query()->where('title', 'Reisepass')->firstOrFail();
        $this->assertTrue($service->departments->contains($department));
        $this->assertSame(5, (int) $service->contacts()->first()?->pivot->sort_order);

        // One phone number, every usage updates.
        $department->update(['phone' => '08233 9999']);
        $this->assertSame('08233 9999', $service->departments()->first()?->phone);
    }

    public function test_category_of_another_context_is_rejected(): void
    {
        $eventCategory = Category::create(['context' => 'event', 'name' => 'Kultur', 'slug' => 'kultur']);

        $this->actingAsAdmin($this->admin)->post($this->adminUrl('artikel'), ['title' => 'X', 'category_id' => $eventCategory->id])
            ->assertSessionHasErrors('category_id');
    }

    public function test_unchecking_all_tags_clears_relation(): void
    {
        $tag = Tag::create(['name' => 'Feuerwehr', 'slug' => 'feuerwehr']);
        $this->actingAsAdmin($this->admin)->post($this->adminUrl('artikel'), ['title' => 'Mit Tag', 'tags__present' => '1', 'tags' => [$tag->id]]);
        $article = Article::query()->where('title', 'Mit Tag')->firstOrFail();
        $this->assertCount(1, $article->tags);

        $this->actingAsAdmin($this->admin)->put($this->adminUrl('artikel/'.$article->id), ['title' => 'Mit Tag', 'tags__present' => '1'])->assertSessionHasNoErrors();
        $this->assertCount(0, $article->fresh()?->tags ?? []);
    }

    public function test_validation_errors_are_accessible(): void
    {
        $this->actingAsAdmin($this->admin)->from($this->adminUrl('veranstaltungen/neu'))
            ->post($this->adminUrl('veranstaltungen'), ['title' => '', 'starts_at' => '2026-07-04T18:00', 'ends_at' => '2026-07-04T17:00', 'url' => 'javascript:alert(1)'])
            ->assertSessionHasErrors(['title', 'ends_at', 'url']);

        $this->actingAsAdmin($this->admin)->from($this->adminUrl('veranstaltungen/neu'))->followingRedirects()
            ->post($this->adminUrl('veranstaltungen'), ['title' => '', 'starts_at' => '2026-07-04T18:00'])
            ->assertSee('Bitte prüfen Sie Ihre Angaben')
            ->assertSee('aria-invalid="true"', false)
            ->assertSee('<title>Fehler:', false);
    }

    public function test_invalid_dst_time_uses_site_time_rules(): void
    {
        $this->actingAsAdmin($this->admin)->post($this->adminUrl('veranstaltungen'), ['title' => 'Nachtwanderung', 'starts_at' => '2026-03-29T02:30'])
            ->assertSessionHasErrors(['starts_at' => 'Diese Uhrzeit existiert wegen der Zeitumstellung nicht.']);
    }

    public function test_admin_pages_are_not_cacheable(): void
    {
        $response = $this->actingAsAdmin($this->admin)->get($this->adminUrl('artikel'));

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $response->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
    }
}
