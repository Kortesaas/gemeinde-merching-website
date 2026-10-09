<?php

namespace Tests\Feature\Content;

use App\Admin\ResourceRegistry;
use App\Enums\PublicationStatus;
use App\Models\Media;
use App\Services\Content\ContentUsage;
use App\Services\Content\ProposalService;
use App\Services\Content\RevisionService;
use App\Services\Routing\RouteManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesContent;
use Tests\TestCase;

class MediaFoundationTest extends TestCase
{
    use CreatesContent,RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake((string) config('uploads.disk'));
    }

    private function upload(array $extra = []): Media
    {
        $this->actingAsAdmin($this->createUser())->post($this->adminUrl('medien'), [
            'title' => 'Testbild', 'file' => UploadedFile::fake()->image('Bild.png', 40, 30), ...$extra,
        ])->assertSessionHasNoErrors()->assertRedirect();

        return Media::query()->latest('id')->firstOrFail();
    }

    public function test_upload_records_private_generated_name_and_verified_metadata(): void
    {
        $m = $this->upload(['caption' => 'Bildunterschrift', 'copyright' => 'Testquelle', 'creator' => 'Testautor', 'language' => 'de']);
        $this->assertMatchesRegularExpression('#^uploads/\d{4}/\d{2}/[a-z0-9]{40}\.png$#', $m->file_path);
        $this->assertSame('Bild.png', $m->original_filename);
        $this->assertSame('image/png', $m->mime_type);
        $this->assertSame(40, $m->width);
        $this->assertSame(30, $m->height);
        $bytes = Storage::disk((string) config('uploads.disk'))->get($m->file_path);
        $this->assertSame(strlen($bytes), $m->size_bytes);
        $this->assertSame(hash('sha256', $bytes), $m->getAttribute('sha256'));
        $this->assertSame('Testquelle', $m->getAttribute('copyright'));
        $this->assertNull($m->alt_text);
        $this->get('/medien/'.$m->id)->assertNotFound();
        $this->get($this->adminUrl('medien/'.$m->id))->assertOk()->assertSee('Alternativtext')->assertSee('40');
    }

    public function test_fake_image_and_svg_are_rejected_without_files(): void
    {
        $admin = $this->createUser();
        foreach (['a.png' => '<?php echo 1;', 'a.svg' => '<svg><script/></svg>'] as $name => $bytes) {
            $this->actingAsAdmin($admin)->post($this->adminUrl('medien'), ['title' => 'Unsicher', 'file' => UploadedFile::fake()->createWithContent($name, $bytes)])->assertSessionHasErrors('file');
        }
        $this->assertDatabaseCount('media', 0);
        $this->assertSame([], Storage::disk((string) config('uploads.disk'))->allFiles());
    }

    public function test_image_pixel_limit_is_enforced(): void
    {
        config(['uploads.max_image_pixels' => 20]);
        $this->actingAsAdmin($this->createUser())->post($this->adminUrl('medien'), ['title' => 'Groß', 'file' => UploadedFile::fake()->image('a.png', 10, 10)])->assertSessionHasErrors('file');
        $this->assertSame([], Storage::disk((string) config('uploads.disk'))->allFiles());
    }

    public function test_meaningful_image_requires_alt_before_publication_and_failure_cleans_up(): void
    {
        $this->actingAsAdmin($this->createUser())->post($this->adminUrl('medien'), ['title' => 'Bild', 'file' => UploadedFile::fake()->image('a.png'), 'status' => 'published'])->assertSessionHasErrors('alt_text');
        $this->assertDatabaseCount('media', 0);
        $this->assertSame([], Storage::disk((string) config('uploads.disk'))->allFiles());
    }

    public function test_decorative_flag_is_explicit_and_persists_empty_alt(): void
    {
        $m = $this->upload(['is_decorative' => 1, 'status' => 'published']);
        $this->assertTrue($m->is_decorative);
        $this->assertSame('', $m->alt_text);
        $this->assertTrue($m->hasAccessibleAlternative());
    }

    public function test_public_delivery_requires_reachable_owner_and_uses_no_session(): void
    {
        $m = $this->upload(['alt_text' => 'Rathaus', 'status' => 'published']);
        $this->get('/medien/'.$m->id)->assertNotFound();
        $p = $this->page();
        $p->media()->attach($m->id);
        $r = $this->get('/medien/'.$m->id)->assertOk()->assertHeader('Content-Type', 'image/png')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertSame([], $r->headers->getCookies());
        $this->assertNotEmpty($r->streamedContent());
        $p->delete();
        $this->get('/medien/'.$m->id)->assertNotFound();
    }

    public function test_media_references_block_purge_and_detaching_keeps_file(): void
    {
        $m = $this->upload();
        $p = $this->page([], PublicationStatus::Draft);
        $p->media()->attach($m->id);
        $this->assertCount(1, app(ContentUsage::class)->mediaOwners($m));
        $p->delete();
        $m->delete();
        $this->actingAsAdmin($this->createUser())->delete($this->adminUrl('medien/'.$m->id.'/endgueltig'))->assertSessionHasErrors('general');
        $p->media()->detach($m->id);
        Storage::disk((string) config('uploads.disk'))->assertExists($m->file_path);
        $this->actingAsAdmin($this->createUser())->delete($this->adminUrl('medien/'.$m->id.'/endgueltig'))->assertSessionHasNoErrors();
        $this->assertModelMissing($m);
        Storage::disk((string) config('uploads.disk'))->assertMissing($m->file_path);
    }

    public function test_historical_media_reference_blocks_purge_after_detaching(): void
    {
        $m = $this->upload();
        $p = $this->page([], PublicationStatus::Draft);
        $p->media()->attach($m->id);
        app(RevisionService::class)->record($p, null);
        $p->media()->detach();
        $m->delete();
        $this->actingAsAdmin($this->createUser())->delete($this->adminUrl('medien/'.$m->id.'/endgueltig'))->assertSessionHasErrors('general');
        Storage::disk((string) config('uploads.disk'))->assertExists($m->file_path);
    }

    public function test_unpublished_media_blocks_page_publication(): void
    {
        $m = $this->upload(['alt_text' => 'Test']);
        $this->actingAsAdmin($this->createUser())->post($this->adminUrl('seiten'), ['title' => 'Mit Bild', 'media' => [$m->id], 'status' => 'published'])->assertSessionHasErrors('media');
        $this->assertDatabaseCount('pages', 0);
    }

    public function test_proposed_media_metadata_stays_private_until_approval(): void
    {
        $m = $this->upload(['alt_text' => 'Alt', 'status' => 'published']);
        $author = $this->userWithPermissions(['media.view', 'media.edit']);
        $reviewer = $this->createUser();
        $s = app(ProposalService::class);
        $proposal = $s->create($m, $author);
        $s->update($proposal, ResourceRegistry::get('media'), ['title' => 'Testbild', 'alt_text' => 'Neu', 'is_decorative' => false], Request::create('/', 'POST'), $author);
        $this->assertSame('Alt', $m->fresh()->alt_text);
        $s->submit($proposal->refresh(), $author);
        $s->apply($proposal->refresh(), $reviewer, false);
        $this->assertSame('Neu', $m->fresh()->alt_text);
        $this->assertSame($m->file_path, $m->fresh()->file_path);
    }

    public function test_editor_cannot_modify_published_media_directly(): void
    {
        $m = $this->upload(['alt_text' => 'Test', 'status' => 'published']);
        $editor = $this->userWithPermissions(['media.view', 'media.edit']);
        $this->actingAsAdmin($editor)->put($this->adminUrl('medien/'.$m->id), ['title' => 'Geändert'])->assertForbidden();
    }

    public function test_failed_file_replacement_retains_old_bytes(): void
    {
        $m = $this->upload(['alt_text' => 'Alt', 'status' => 'published']);
        $previous = $m->file_path;
        $this->actingAsAdmin($this->createUser())->put($this->adminUrl('medien/'.$m->id), ['title' => 'Testbild', 'alt_text' => null, 'file' => UploadedFile::fake()->image('neu.png')])->assertSessionHasErrors('alt_text');
        $this->assertSame($previous, $m->fresh()->file_path);
        $this->assertCount(1, Storage::disk((string) config('uploads.disk'))->allFiles());
    }

    public function test_restoring_incomplete_media_revision_cannot_remove_live_alt(): void
    {
        $m = $this->upload();
        $revision = $m->revisions()->firstOrFail();
        $this->actingAsAdmin($this->createUser())->put($this->adminUrl('medien/'.$m->id), ['title' => 'Testbild', 'alt_text' => 'Geprüft', 'status' => 'published'])->assertSessionHasNoErrors();
        $this->actingAsAdmin($this->createUser())->post($this->adminUrl('medien/'.$m->id.'/versionen/'.$revision->revision_number.'/wiederherstellen'))->assertSessionHasErrors('alt_text');
        $this->assertSame('Geprüft', $m->fresh()->alt_text);
    }

    public function test_private_media_in_a_proposal_cannot_be_approved_or_purged(): void
    {
        $m = $this->upload(['alt_text' => 'Test']);
        $p = $this->page();
        app(RouteManager::class)->assign($p, '/medien-test');
        $author = $this->userWithPermissions(['page.edit', 'page.view']);
        $publisher = $this->createUser();
        $s = app(ProposalService::class);
        $proposal = $s->create($p, $author);
        $s->update($proposal, ResourceRegistry::get('page'), ['title' => $p->title, 'media' => [$m->id]], Request::create('/', 'POST'), $author);
        $s->submit($proposal->refresh(), $author);
        $this->assertSame(0, $p->media()->count());
        $m->delete();
        $this->actingAsAdmin($publisher)->delete($this->adminUrl('medien/'.$m->id.'/endgueltig'))->assertSessionHasErrors('general');
        $this->actingAsAdmin($publisher)->post($this->adminUrl('freigaben/'.$proposal->id.'/freigeben'))->assertSessionHasErrors();
        $this->assertSame(0, $p->media()->count());
    }

    public function test_media_bytes_are_reencoded_and_nonimage_files_still_work(): void
    {
        $image = UploadedFile::fake()->image('test.png', 5, 5);
        file_put_contents($image->getPathname(), 'APPENDED_PRIVATE_METADATA', FILE_APPEND);
        $this->actingAsAdmin($this->createUser())->post($this->adminUrl('medien'), ['title' => 'Test', 'file' => $image])->assertSessionHasNoErrors();
        $m = Media::query()->firstOrFail();
        $bytes = Storage::disk((string) config('uploads.disk'))->get($m->file_path);
        $this->assertStringNotContainsString('APPENDED_PRIVATE_METADATA', $bytes);
        $this->actingAsAdmin($this->createUser())->post($this->adminUrl('medien'), ['title' => 'Datei', 'file' => UploadedFile::fake()->createWithContent('datei.pdf', self::PDF), 'status' => 'published'])->assertSessionHasNoErrors();
        $file = Media::query()->latest('id')->firstOrFail();
        $p = $this->page();
        $p->media()->attach($file->id);
        $this->get('/medien/'.$file->id)->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_new_admin_create_screen_has_usable_file_input_and_language(): void
    {
        $this->actingAsAdmin($this->createUser())->get($this->adminUrl('medien/neu'))->assertOk()->assertSee('type="file"',false)->assertSee('name="language"',false);
    }
}
