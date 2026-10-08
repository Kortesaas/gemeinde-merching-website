<?php

namespace Tests\Feature\Content;

use App\Enums\AccessibilityStatus;
use App\Enums\PublicationStatus;
use App\Models\Document;
use App\Services\Content\ContentUsage;
use App\Services\Routing\RouteManager;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesContent;
use Tests\TestCase;

class DocumentTest extends TestCase
{
    use CreatesContent, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake((string) config('uploads.disk'));
    }

    private function upload(string $name, string $content, array $extra = []): TestResponse
    {
        return $this->actingAsAdmin($this->createUser())->post($this->adminUrl('dokumente'), [
            'title' => 'Satzung',
            'accessibility_status' => 'not_checked',
            'file' => UploadedFile::fake()->createWithContent($name, $content),
            ...$extra,
        ]);
    }

    public function test_metadata_is_derived_from_the_file_and_stored_privately(): void
    {
        $this->upload('Satzung über Gebühren 2026.PDF', self::PDF, ['year' => '2026', 'valid_from' => '2026-01-01'])->assertSessionHasNoErrors();

        $document = Document::query()->firstOrFail();
        $this->assertSame('Satzung über Gebühren 2026.PDF', $document->original_filename);
        $this->assertSame('application/pdf', $document->mime_type);
        $this->assertSame('pdf', $document->extension);
        $this->assertSame(strlen(self::PDF), $document->size_bytes);
        $this->assertSame(hash('sha256', self::PDF), $document->sha256);
        $this->assertMatchesRegularExpression('#^uploads/\d{4}/\d{2}/[a-z0-9]{40}\.pdf$#', $document->file_path);
        $this->assertStringNotContainsString('Satzung', $document->file_path);
        $this->assertSame(2026, $document->year);
        Storage::disk((string) config('uploads.disk'))->assertExists($document->file_path);
        $this->assertStringStartsWith(storage_path('app/private'), config('filesystems.disks.local.root'));
    }

    public function test_new_document_is_never_marked_accessible_by_default(): void
    {
        $this->actingAsAdmin($this->createUser())->post($this->adminUrl('dokumente'), [
            'title' => 'Ohne Angabe',
            'accessibility_status' => 'not_checked',
            'file' => UploadedFile::fake()->createWithContent('a.pdf', self::PDF),
        ]);
        $this->assertSame(AccessibilityStatus::NotChecked, Document::query()->firstOrFail()->accessibility_status);
        $this->assertSame(AccessibilityStatus::NotChecked, (new Document)->accessibility_status);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function unsafeFiles(): array
    {
        return [
            'php as pdf' => ['satzung.pdf', '<?php system($_GET["c"]); ?>'],
            'svg with script' => ['logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'],
            'html' => ['seite.html', '<html><script>alert(1)</script></html>'],
            'executable' => ['tool.exe', "MZ\x90\x00"],
        ];
    }

    #[DataProvider('unsafeFiles')]
    public function test_unsafe_files_are_rejected_and_nothing_is_stored(string $name, string $content): void
    {
        $this->upload($name, $content)->assertSessionHasErrors('file');

        $this->assertSame(0, Document::count());
        $this->assertSame([], Storage::disk((string) config('uploads.disk'))->allFiles());
    }

    public function test_alternative_provided_requires_the_alternative(): void
    {
        $this->upload('a.pdf', self::PDF, ['accessibility_status' => 'alternative_provided'])->assertSessionHasErrors('accessible_alternative_id');
    }

    public function test_supersession_cannot_form_a_cycle(): void
    {
        $old = $this->document(['title' => 'Satzung 2020']);
        $new = $this->document(['title' => 'Satzung 2026']);
        $new->forceFill(['replaces_document_id' => $old->id])->save();

        $this->assertTrue($old->isSuperseded());
        $this->actingAsAdmin($this->createUser())->put($this->adminUrl('dokumente/'.$old->id), [
            'title' => 'Satzung 2020', 'accessibility_status' => 'not_checked', 'replaces_document_id' => $new->id,
        ])->assertSessionHasErrors('replaces_document_id');
    }

    public function test_references_prevent_permanent_deletion_and_file_destruction(): void
    {
        $admin = $this->createUser();
        $document = $this->document();
        $page = $this->page(['title' => 'Haushaltspläne']);
        $page->documents()->attach($document->id, ['slot' => 'downloads', 'group_label' => '2026', 'sort_order' => 3]);

        $usages = app(ContentUsage::class)->of($document);
        $this->assertCount(1, $usages);
        $this->assertSame('Downloads › 2026', $usages[0]['context']);
        $this->actingAsAdmin($admin)->get($this->adminUrl('dokumente/'.$document->id))->assertSee('Haushaltspläne')->assertSee('Downloads › 2026');

        // An owner in the recycle bin still counts as a reference.
        $page->delete();
        $document->delete();
        $this->actingAsAdmin($admin)->delete($this->adminUrl('dokumente/'.$document->id.'/endgueltig'))->assertSessionHasErrors('general');
        $this->assertNotNull(Document::withTrashed()->find($document->id));
        Storage::disk((string) config('uploads.disk'))->assertExists($document->file_path);

        // The database restricts as a second line of defence.
        $this->expectException(QueryException::class);
        $document->forceDelete();
    }

    public function test_removing_a_placement_keeps_document_and_file_and_unused_document_can_be_purged(): void
    {
        $admin = $this->createUser();
        $document = $this->document();
        $page = $this->page();

        $this->actingAsAdmin($admin)->post($this->adminUrl('seiten/'.$page->id.'/zuordnungen'), [
            'kind' => 'documents', 'item_id' => $document->id, 'slot' => 'downloads', 'group_label' => '2026', 'sort_order' => 3,
        ])->assertSessionHasNoErrors();
        $pivotId = (int) $page->documents()->first()?->pivot->id;

        $this->actingAsAdmin($admin)->delete($this->adminUrl("seiten/{$page->id}/zuordnungen/documents/{$pivotId}"))->assertRedirect();
        $this->assertNotNull($document->fresh());
        Storage::disk((string) config('uploads.disk'))->assertExists($document->file_path);

        $this->actingAsAdmin($admin)->delete($this->adminUrl('dokumente/'.$document->id));
        $this->actingAsAdmin($admin)->delete($this->adminUrl('dokumente/'.$document->id.'/endgueltig'))->assertSessionHasNoErrors();
        $this->assertModelMissing($document);
        Storage::disk((string) config('uploads.disk'))->assertMissing($document->file_path);
    }

    public function test_placement_slots_are_validated(): void
    {
        $document = $this->document();
        $page = $this->page();

        $this->actingAsAdmin($this->createUser())->post($this->adminUrl('seiten/'.$page->id.'/zuordnungen'), [
            'kind' => 'documents', 'item_id' => $document->id, 'slot' => '<script>', 'sort_order' => 0,
        ])->assertSessionHasErrors('slot');
        $this->assertSame(0, $page->documents()->count());
    }

    public function test_published_document_is_served_publicly_with_safe_headers(): void
    {
        $document = $this->document(['title' => 'Satzung']);
        app(RouteManager::class)->assign($document, '/wp-content/uploads/2023/05/satzung.pdf');

        $response = $this->get('/wp-content/uploads/2023/05/satzung.pdf');
        $response->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('inline; filename=test.pdf', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('sandbox', (string) $response->headers->get('Content-Security-Policy'));
        $this->assertSame([], $response->headers->getCookies());
        $this->assertSame(self::PDF, $response->streamedContent());
    }

    public function test_draft_document_is_not_public_but_downloadable_in_backend(): void
    {
        $document = $this->document([], PublicationStatus::Draft);
        app(RouteManager::class)->assign($document, '/dokumente/entwurf.pdf');

        $this->get('/dokumente/entwurf.pdf')->assertNotFound();
        $this->get($this->adminUrl('dokumente/'.$document->id.'/datei'))->assertRedirect($this->adminUrl('login'));
        $this->actingAsAdmin($this->createUser())->get($this->adminUrl('dokumente/'.$document->id.'/datei'))->assertOk();
    }
}
