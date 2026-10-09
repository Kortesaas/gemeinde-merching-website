<?php

namespace Tests\Feature\Public;

use App\Models\Article;
use App\Models\ContentProposal;
use App\Models\Media;
use App\Models\Page;
use App\Models\PublicRoute;
use App\Models\Service;
use App\Models\SiteSettings;
use App\Models\SourceReference;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DevelopmentDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

/**
 * Fresh database → migrations → roles → development demo seed → the public
 * site renders every major template with realistic, clearly fictional data.
 * Strict model mode turns lazy-loading mistakes in templates into failures.
 */
class DevelopmentDemoContentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake((string) config('uploads.disk'));
    }

    public function test_demo_seed_runs_on_a_fresh_database_and_marks_its_records(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DevelopmentDemoSeeder::class);

        $this->assertGreaterThan(10, Article::query()->count());
        $this->assertGreaterThan(15, Service::query()->count());
        $this->assertTrue(SourceReference::query()->where('source_system', DevelopmentDemoSeeder::SOURCE_SYSTEM)->exists());
        $this->assertSame(4, ContentProposal::query()->where('status', 'submitted')->count());
        $this->assertNotNull(SiteSettings::query()->find(1)?->homepage_media_id);
        // Demo accounts have unknown random passwords; nobody can sign in with shared credentials.
        $this->assertTrue(User::query()->where('email', 'like', '%@beispiel.invalid')->exists());
    }

    public function test_demo_seed_refuses_a_database_with_existing_content(): void
    {
        Page::create(['title' => 'Vorhandener Inhalt']);

        $this->expectException(RuntimeException::class);
        $this->seed(DevelopmentDemoSeeder::class);
    }

    public function test_every_public_template_renders_with_demo_content_without_cookies(): void
    {
        $this->seed(DevelopmentDemoSeeder::class);

        $paths = [
            '/', '/buergerservice', '/buergerservice/a-z', '/buergerservice?online=1', '/aktuelles', '/aktuelles?archiv=1', '/veranstaltungen', '/veranstaltungen?archiv=1',
            '/bekanntmachungen', '/bekanntmachungen?archiv=1', '/dokumente', '/dokumente?archiv=1', '/verzeichnisse', '/suche?q=Hund', '/suche?q=Perso&type=service',
            '/suche?q=nichtvorhandenerbegriff', '/rathaus', '/rathaus/buergerbuero', '/vereine/sportverein-gruen-weiss', '/gemeinderat', '/gemeinderat/2020-2026',
            '/galerie/ortsansichten', '/leben/freizeit-am-see', '/ortsrecht', '/buergerservice/lebenslagen/umzug-nach-merching',
        ];
        foreach (Service::query()->with('canonicalRoute')->get() as $service) {
            if ($service->publicPath() !== null) {
                $paths[] = $service->publicPath();
            }
        }
        foreach (['article', 'event', 'notice'] as $type) {
            foreach (PublicRoute::query()->where('routable_type', $type)->where('is_canonical', true)->limit(6)->pluck('path') as $path) {
                $paths[] = $path;
            }
        }

        foreach ($paths as $path) {
            $response = $this->get($path);
            $this->assertContains($response->getStatusCode(), [200], $path);
            $this->assertSame([], $response->headers->getCookies(), $path);
            $response->assertDontSee('getKey())', false);
        }
    }

    public function test_demo_media_is_delivered_including_images_in_several_galleries(): void
    {
        $this->seed(DevelopmentDemoSeeder::class);

        // The portrait illustration is placed in both demo galleries.
        $media = Media::query()->where('title', 'like', 'Maibaum%')->firstOrFail();
        $this->get('/medien/'.$media->id.'?width=480')->assertOk()->assertHeader('Content-Type', 'image/webp');
        // The homepage image is public through the site settings.
        $this->get('/medien/'.SiteSettings::query()->find(1)->homepage_media_id)->assertOk();
        // A draft image without alternative text stays private.
        $private = Media::query()->where('title', 'Pressefoto ohne Alternativtext')->firstOrFail();
        $this->get('/medien/'.$private->id)->assertNotFound();
    }
}
