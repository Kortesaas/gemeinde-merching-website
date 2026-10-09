<?php

namespace Tests\Feature\Content;

use App\Admin\Resources\GalleryResource;
use App\Admin\Resources\PageResource;
use App\Enums\PublicationStatus;
use App\Models\ContentBlock;
use App\Models\Department;
use App\Models\Event;
use App\Models\ExternalResource;
use App\Models\Gallery;
use App\Models\Media;
use App\Models\Page;
use App\Models\Person;
use App\Models\Service;
use App\Services\Content\ProposalDiff;
use App\Services\Content\ProposalService;
use App\Services\Content\ReferenceProtection;
use App\Services\Content\RevisionService;
use App\Services\Quality\QualityChecks;
use App\Services\Routing\RouteManager;
use App\Services\Search\SearchVisibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesContent;
use Tests\TestCase;

class ContentCompositionTest extends TestCase
{
    use CreatesContent, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake((string) config('uploads.disk'));
    }

    private function image(bool $published = true): Media
    {
        $this->actingAsAdmin($this->createUser())->post($this->adminUrl('medien'), [
            'title' => 'Synthetisches Bild', 'alt_text' => 'Blick auf eine Testlandschaft', 'file' => UploadedFile::fake()->image('test.png'),
            'status' => $published ? 'published' : 'draft', 'publish_at' => $published ? '2026-01-01T10:00' : null,
        ])->assertSessionHasNoErrors();

        return Media::query()->latest('id')->firstOrFail();
    }

    public function test_blocks_preserve_legacy_markdown_and_render_in_explicit_order_without_cookies(): void
    {
        $this->actingAsAdmin($this->createUser())->post($this->adminUrl('seiten'), [
            'title' => 'Testseite', 'body' => 'Bestehender **Text**.', 'status' => 'published', 'publish_at' => '2026-01-01T10:00', 'public_path' => '/testseite',
            'blocks' => [
                ['type' => 'text', 'text' => 'Neuer zweiter Absatz.', 'sort_order' => 20],
                ['type' => 'heading', 'heading' => 'Erster Abschnitt', 'heading_level' => 2, 'sort_order' => 1],
                ['type' => 'accordion', 'heading' => 'Weitere Information', 'text' => 'Aufklappbarer Text.', 'sort_order' => 30],
            ],
        ])->assertSessionHasNoErrors();
        $page = Page::query()->firstOrFail();
        $this->assertSame(['heading', 'text', 'accordion'], $page->blocks()->pluck('type')->all());
        $this->assertSame([0, 1, 2], $page->blocks()->pluck('sort_order')->all());
        $response = $this->get('/testseite')->assertOk()->assertSee('Bestehender <strong>Text</strong>', false)->assertSeeInOrder(['Erster Abschnitt', 'Neuer zweiter Absatz', 'Weitere Information'])->assertSee('<details class="accordion">', false);
        $this->assertSame([], $response->headers->getCookies());
        $this->assertDatabaseHas('search_entries', ['content_type' => 'page', 'content_id' => $page->id]);
        $this->assertStringContainsString('Neuer zweiter Absatz', DB::table('search_entries')->where('content_type', 'page')->value('body'));
    }

    public static function unsafeBlocks(): array
    {
        return [
            'html' => [['type' => 'text', 'text' => '<b>HTML</b>', 'sort_order' => 0]],
            'script' => [['type' => 'text', 'text' => '<script>alert(1)</script>', 'sort_order' => 0]],
            'iframe' => [['type' => 'text', 'text' => '<iframe src="https://example.test"></iframe>', 'sort_order' => 0]],
            'unsafe link' => [['type' => 'text', 'text' => '[Link](javascript:alert(1))', 'sort_order' => 0]],
            'external image' => [['type' => 'text', 'text' => '![Bild](https://example.test/a.png)', 'sort_order' => 0]],
            'unknown type' => [['type' => 'html', 'text' => 'test', 'sort_order' => 0]],
            'negative order' => [['type' => 'text', 'text' => 'test', 'sort_order' => -1]],
            'large order' => [['type' => 'text', 'text' => 'test', 'sort_order' => 65536]],
            'h1' => [['type' => 'heading', 'heading' => 'Titel', 'heading_level' => 1, 'sort_order' => 0]],
            'heading in text' => [['type' => 'text', 'text' => '### Unterüberschrift', 'sort_order' => 0]],
            'missing reference' => [['type' => 'downloads', 'sort_order' => 0]],
        ];
    }

    #[DataProvider('unsafeBlocks')]
    public function test_invalid_compositions_are_rejected_atomically(array $block): void
    {
        $this->actingAsAdmin($this->createUser())->post($this->adminUrl('seiten'), ['title' => 'Ungültig', 'blocks' => [$block]])->assertSessionHasErrors('blocks');
        $this->assertDatabaseCount('pages', 0);
        $this->assertDatabaseCount('content_blocks', 0);
    }

    public function test_heading_hierarchy_blocks_publication_but_can_be_reviewed_as_a_draft(): void
    {
        $admin = $this->createUser();
        $data = ['title' => 'Überschriften', 'blocks' => [['type' => 'heading', 'heading' => 'Unterpunkt', 'heading_level' => 3, 'sort_order' => 0]]];
        $this->actingAsAdmin($admin)->post($this->adminUrl('seiten'), $data)->assertSessionHasNoErrors();
        $page = Page::query()->firstOrFail();
        $this->assertContains('blocks.heading', array_map(fn ($i) => $i->code, app(QualityChecks::class)->inspect($page)));
        $this->put($this->adminUrl('seiten/'.$page->id), $data + ['status' => 'published', 'publish_at' => '2026-01-01T10:00'])->assertSessionHasErrors('blocks');
        $this->assertSame(PublicationStatus::Draft, $page->fresh()->status);
    }

    public function test_revision_restore_recovers_composition_and_older_snapshots_clear_it(): void
    {
        $admin = $this->createUser();
        $resource = app(PageResource::class);
        $page = $this->page([], PublicationStatus::Draft);
        $legacy = app(RevisionService::class)->record($page, $admin);
        // Simulate a pre-migration fixture; application revisions remain immutable.
        DB::table('content_revisions')->where('id', $legacy->id)->update(['snapshot' => json_encode(['schema' => 2, 'attributes' => $legacy->snapshot['attributes'], 'relations' => $legacy->snapshot['relations'], 'collections' => []])]);
        $legacy->refresh();
        $resource->save($page, ['blocks' => [['type' => 'text', 'text' => 'Erster Stand', 'sort_order' => 0]]], Request::create('/'), $admin);
        $first = $page->revisions()->firstOrFail();
        $resource->save($page, ['blocks' => [['type' => 'text', 'text' => 'Zweiter Stand', 'sort_order' => 0]]], Request::create('/'), $admin);
        app(RevisionService::class)->restore($first, $admin);
        $this->assertSame('Erster Stand', $page->blocks()->firstOrFail()->text);
        app(RevisionService::class)->restore($legacy, $admin);
        $this->assertSame(0, $page->blocks()->count());
        $this->assertSame(5, $page->revisions()->count());
    }

    public function test_block_proposal_diff_and_approval_preserve_live_content_until_review(): void
    {
        $author = $this->userWithPermissions(['page.edit', 'page.view']);
        $reviewer = $this->createUser();
        $page = $this->page();
        $resource = app(PageResource::class);
        $proposals = app(ProposalService::class);
        $proposal = $proposals->create($page, $author);
        $proposals->update($proposal, $resource, ['blocks' => [['type' => 'text', 'text' => 'Vorgeschlagener Absatz', 'sort_order' => 0]]], Request::create('/'), $author);
        $this->assertSame(0, $page->blocks()->count());
        $diff = app(ProposalDiff::class)->rows($proposal->fresh(), $resource, $page);
        $this->assertSame('Inhaltsbausteine', $diff[0]['label']);
        $this->assertStringContainsString('Vorgeschlagener Absatz', $diff[0]['after']);
        $this->assertStringNotContainsString('Vorgeschlagener Absatz', DB::table('search_entries')->where('content_type', 'page')->value('body'));
        $proposals->submit($proposal, $author);
        $proposals->apply($proposal, $reviewer, false);
        $this->assertSame('Vorgeschlagener Absatz', $page->blocks()->firstOrFail()->text);
        $this->assertStringContainsString('Vorgeschlagener Absatz', DB::table('search_entries')->where('content_type', 'page')->value('body'));
    }

    public function test_block_references_protect_deleted_owners_and_retained_history(): void
    {
        $admin = $this->createUser();
        $document = $this->document();
        $page = $this->page([], PublicationStatus::Draft);
        $resource = app(PageResource::class);
        $resource->save($page, ['blocks' => [['type' => 'downloads', 'document_id' => $document->id, 'sort_order' => 0]]], Request::create('/'), $admin);
        $page->delete();
        $document->delete();
        $this->assertNotEmpty(app(ReferenceProtection::class)->usages($document));
        $this->actingAsAdmin($admin)->delete($this->adminUrl('dokumente/'.$document->id.'/endgueltig'))->assertSessionHasErrors('general');
        $page->blocks()->delete();
        $this->assertNotEmpty(app(ReferenceProtection::class)->usages($document));
        Storage::disk((string) config('uploads.disk'))->assertExists($document->file_path);
    }

    public function test_soft_deleted_reference_is_hidden_and_prevents_publication(): void
    {
        $admin = $this->createUser();
        $document = $this->document();
        $page = $this->page();
        app(RouteManager::class)->assign($page, '/referenzen');
        $page->blocks()->create(['type' => 'downloads', 'document_id' => $document->id, 'sort_order' => 0]);
        $document->delete();
        $this->get('/referenzen')->assertOk()->assertDontSee($document->title);
        $this->actingAsAdmin($admin)->put($this->adminUrl('seiten/'.$page->id), ['title' => $page->title])->assertSessionHasErrors('blocks');
    }

    public function test_gallery_uses_central_images_with_contextual_overrides_and_opt_in_route(): void
    {
        $media = $this->image();
        $admin = $this->createUser();
        $this->actingAsAdmin($admin)->post($this->adminUrl('galerien'), ['title' => 'Testgalerie', 'status' => 'published', 'publish_at' => '2026-01-01T10:00', 'items' => [['media_id' => $media->id, 'sort_order' => 0, 'caption' => 'Kontextbild', 'alt_override' => 'Testlandschaft im Frühling', 'alt_context' => 'Jahreszeit ist hier relevant']]])->assertSessionHasNoErrors();
        $gallery = Gallery::query()->firstOrFail();
        $this->assertNull($gallery->publicPath());
        $this->get('/medien/'.$media->id)->assertNotFound();
        app(RouteManager::class)->assign($gallery, '/testgalerie');
        $this->get('/testgalerie')->assertOk()->assertSee('alt="Testlandschaft im Frühling"', false)->assertSee('Kontextbild');
        $this->get('/medien/'.$media->id)->assertOk();
        $this->assertSame(1, Media::query()->count());
        $this->assertDatabaseHas('search_entries', ['content_type' => 'gallery', 'content_id' => $gallery->id]);
        $this->assertNotEmpty(app(ReferenceProtection::class)->usages($media));
    }

    public function test_unrouted_gallery_is_public_in_a_public_block_context(): void
    {
        $media = $this->image();
        $gallery = Gallery::create(['title' => 'Kontextgalerie']);
        $gallery->items()->create(['media_id' => $media->id, 'sort_order' => 0]);
        $gallery->forceFill(['status' => PublicationStatus::Published, 'publish_at' => now()->subDay()])->save();
        $page = $this->page();
        app(RouteManager::class)->assign($page, '/galeriekontext');
        $page->blocks()->create(['type' => 'gallery', 'gallery_id' => $gallery->id, 'sort_order' => 0]);
        $this->get('/galeriekontext')->assertOk()->assertSee('Blick auf eine Testlandschaft');
        $this->get('/medien/'.$media->id)->assertOk();
        $page->delete();
        $this->get('/medien/'.$media->id)->assertNotFound();
    }

    public function test_gallery_requires_images_publication_and_justified_alt_override(): void
    {
        $private = $this->image(false);
        $admin = $this->createUser();
        $this->actingAsAdmin($admin)->post($this->adminUrl('galerien'), ['title' => 'Galerie', 'status' => 'published', 'publish_at' => '2026-01-01T10:00', 'items' => [['media_id' => $private->id, 'sort_order' => 0]]])->assertSessionHasErrors('items');
        $this->post($this->adminUrl('galerien'), ['title' => 'Galerie', 'items' => [['media_id' => $private->id, 'sort_order' => 0, 'alt_override' => 'Andere Beschreibung']]])->assertSessionHasErrors('items');
        $this->post($this->adminUrl('galerien'), ['title' => 'Leer', 'status' => 'published', 'publish_at' => '2026-01-01T10:00'])->assertSessionHasErrors('items');
        $this->assertDatabaseCount('galleries', 0);
    }

    public function test_gallery_revision_and_proposal_restore_order_caption_and_override(): void
    {
        $media = $this->image();
        $admin = $this->createUser();
        $resource = app(GalleryResource::class);
        $gallery = $resource->save(null, ['title' => 'Galerie', 'items' => [['media_id' => $media->id, 'sort_order' => 0, 'caption' => 'Erste Fassung']]], Request::create('/'), $admin);
        $first = $gallery->revisions()->firstOrFail();
        $resource->save($gallery, ['items' => [['media_id' => $media->id, 'sort_order' => 0, 'caption' => 'Zweite Fassung']]], Request::create('/'), $admin);
        app(RevisionService::class)->restore($first, $admin);
        $this->assertSame('Erste Fassung', $gallery->items()->firstOrFail()->caption);
        $resource->save($gallery, ['status' => 'published', 'publish_at' => '2026-01-01T10:00'], Request::create('/'), $admin);
        $author = $this->userWithPermissions(['gallery.edit']);
        $proposal = app(ProposalService::class)->create($gallery, $author);
        app(ProposalService::class)->update($proposal, $resource, ['items' => [['media_id' => $media->id, 'sort_order' => 0, 'caption' => 'Vorgeschlagen']]], Request::create('/'), $author);
        $this->assertSame('Erste Fassung', $gallery->items()->firstOrFail()->caption);
        app(ProposalService::class)->submit($proposal, $author);
        app(ProposalService::class)->apply($proposal, $admin, false);
        $this->assertSame('Vorgeschlagen', $gallery->items()->firstOrFail()->caption);
    }

    public function test_focal_point_is_validated_and_revisionable_without_changing_bytes(): void
    {
        $media = $this->image(false);
        $path = $media->file_path;
        $hash = hash('sha256', Storage::disk((string) config('uploads.disk'))->get($path));
        $first = $media->revisions()->firstOrFail();
        $admin = $this->createUser();
        $this->actingAsAdmin($admin)->put($this->adminUrl('medien/'.$media->id), ['title' => $media->title, 'focal_x' => 27.5, 'focal_y' => 73])->assertSessionHasNoErrors();
        $this->assertSame('27.50', $media->fresh()->focal_x);
        $this->assertSame($hash, hash('sha256', Storage::disk((string) config('uploads.disk'))->get($path)));
        $this->put($this->adminUrl('medien/'.$media->id), ['title' => $media->title, 'focal_x' => 101])->assertSessionHasErrors('focal_x');
        app(RevisionService::class)->restore($first, $admin);
        $this->assertSame('50.00', $media->fresh()->focal_x);
    }

    public function test_editor_cannot_directly_change_published_composition(): void
    {
        $page = $this->page();
        $editor = $this->userWithPermissions(['page.edit', 'page.view']);
        $this->actingAsAdmin($editor)->put($this->adminUrl('seiten/'.$page->id), ['title' => 'Verboten', 'blocks' => []])->assertForbidden();
        $this->assertSame(0, $page->blocks()->count());
        $this->get($this->adminUrl('seiten/'.$page->id))->assertOk()->assertSee('Inhaltsbausteine');
    }

    public function test_all_reference_block_types_use_real_records_and_safe_public_rendering(): void
    {
        $image = $this->image();
        $document = $this->document();
        $person = Person::create(['last_name' => 'Testkontakt', 'phone' => '0123 456', 'is_active' => true]);
        $department = Department::create(['name' => 'Teststelle', 'is_active' => true]);
        $service = Service::create(['title' => 'Testleistung']);
        $service->forceFill(['status' => PublicationStatus::Published, 'publish_at' => now()->subDay()])->save();
        app(RouteManager::class)->assign($service, '/testleistung');
        $event = Event::create(['title' => 'Testveranstaltung', 'starts_at' => now()->addDay()]);
        $event->forceFill(['status' => PublicationStatus::Published, 'publish_at' => now()->subDay(), 'operational_status' => 'cancelled'])->save();
        app(RouteManager::class)->assign($event, '/testveranstaltung');
        $external = ExternalResource::create(['title' => 'Testportal', 'url' => 'https://example.test/portal', 'type' => 'portal', 'privacy_note' => 'Externer Anbieter']);
        $external->forceFill(['status' => PublicationStatus::Published, 'publish_at' => now()->subDay()])->save();
        $blocks = [];
        foreach ([['image', 'media_id', $image], ['downloads', 'document_id', $document], ['contact', 'person_id', $person], ['department', 'department_id', $department], ['services', 'service_id', $service], ['events', 'event_id', $event], ['external', 'external_resource_id', $external]] as $index => [$type, $column, $record]) {
            $blocks[] = ['type' => $type, $column => $record->id, 'sort_order' => $index];
        }
        $blocks[] = ['type' => 'callout', 'heading' => 'Testhinweis', 'text' => 'Bitte Testunterlagen beachten.', 'sort_order' => 10];
        $this->actingAsAdmin($this->createUser())->post($this->adminUrl('seiten'), ['title' => 'Bausteintest', 'blocks' => $blocks, 'status' => 'published', 'publish_at' => '2026-01-01T10:00', 'public_path' => '/bausteintest'])->assertSessionHasNoErrors();
        $this->get('/bausteintest')->assertOk()->assertSee('Testkontakt')->assertSee('Teststelle')->assertSee('/testleistung')->assertSee('/testveranstaltung')->assertSee('Abgesagt')->assertSee('Testportal')->assertSee('Testhinweis')->assertDontSee('<iframe', false);
        $block = ContentBlock::query()->where('type', 'contact')->firstOrFail();
        $this->assertSame($person->id, $block->person->id);
        $this->assertSame('/bausteintest', app(SearchVisibility::class)->path($person));
    }

    public function test_private_proposal_alone_protects_its_new_reference_from_purge(): void
    {
        $document = $this->document();
        $page = $this->page();
        $author = $this->userWithPermissions(['page.edit']);
        $proposal = app(ProposalService::class)->create($page, $author);
        app(ProposalService::class)->update($proposal, app(PageResource::class), ['blocks' => [['type' => 'downloads', 'document_id' => $document->id, 'sort_order' => 0]]], Request::create('/'), $author);
        $this->assertSame(0, $page->blocks()->count());
        $document->delete();
        $this->actingAsAdmin($this->createUser())->delete($this->adminUrl('dokumente/'.$document->id.'/endgueltig'))->assertSessionHasErrors('general');
        $this->assertNotNull($document->fresh());
    }

    public function test_truncated_editor_forms_fail_instead_of_silently_dropping_rows(): void
    {
        $this->actingAsAdmin($this->createUser())->post($this->adminUrl('seiten'), ['title' => 'Test', '_form_started' => 1, 'blocks' => [['type' => 'text', 'text' => 'Testtext', 'sort_order' => 0]]])->assertSessionHasErrors('_form_complete');
        $this->assertDatabaseCount('pages', 0);
    }

    public function test_duplicate_gallery_items_and_cross_type_references_are_rejected(): void
    {
        $image = $this->image();
        $document = $this->document();
        $this->actingAsAdmin($this->createUser())->post($this->adminUrl('galerien'), ['title' => 'Test', 'items' => [['media_id' => $image->id, 'sort_order' => 0], ['media_id' => $image->id, 'sort_order' => 1]]])->assertSessionHasErrors('items');
        $this->post($this->adminUrl('seiten'), ['title' => 'Test', 'blocks' => [['type' => 'image', 'media_id' => $image->id, 'document_id' => $document->id, 'sort_order' => 0]]])->assertSessionHasErrors('blocks');
    }

    public function test_browser_shaped_rows_with_unused_nullable_fields_save_correctly(): void
    {
        $blank = array_fill_keys(ContentBlock::COLUMNS, null);
        $this->actingAsAdmin($this->createUser())->post($this->adminUrl('seiten'), [
            'title' => 'Vollständiges Formular', '_form_started' => 1, '_form_complete' => 1,
            'blocks' => [
                [...$blank, 'type' => 'heading', 'heading' => 'Abschnitt', 'heading_level' => 2, 'sort_order' => 1],
                [...$blank, 'type' => 'text', 'text' => 'Testtext', 'sort_order' => 2],
                [...$blank, 'sort_order' => 3],
            ],
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('content_blocks', 2);
    }
}
