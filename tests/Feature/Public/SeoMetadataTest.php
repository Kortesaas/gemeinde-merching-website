<?php

namespace Tests\Feature\Public;

use App\Enums\EventOperationalStatus;
use App\Enums\PublicationStatus;
use App\Models\Event;
use App\Models\Gallery;
use App\Models\Location;
use App\Models\Media;
use App\Models\Service;
use App\Models\SiteSettings;
use App\Services\Content\MediaStorage;
use App\Services\Routing\RouteManager;
use App\Support\Routing\PublicPath;
use Carbon\CarbonImmutable;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesContent;
use Tests\TestCase;

class SeoMetadataTest extends TestCase
{
    use CreatesContent, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake((string) config('uploads.disk'));
        config(['app.name' => 'Gemeinde Merching', 'app.url' => 'http://preview.test']);
    }

    private function dom(string $html): DOMXPath
    {
        $document = new DOMDocument;
        @$document->loadHTML('<?xml encoding="utf-8"?>'.$html);

        return new DOMXPath($document);
    }

    private function meta(DOMXPath $dom, string $key): string
    {
        $nodes = $dom->query('//head/meta[@name="'.$key.'" or @property="'.$key.'"]/@content');
        $this->assertCount(1, $nodes);

        return $nodes->item(0)->nodeValue;
    }

    private function medium(bool $public = true): Media
    {
        $media = new Media(['title' => 'Testbild', 'alt_text' => 'Blick auf das Rathaus']);
        app(MediaStorage::class)->attach($media, UploadedFile::fake()->image('share.jpg', 1600, 900));
        $media->forceFill(['status' => $public ? PublicationStatus::Published : PublicationStatus::Draft, 'publish_at' => now()->subDay()])->save();

        return $media;
    }

    public function test_home_has_complete_metadata_and_default_branding_on_production_urls(): void
    {
        $response = $this->get('/')->assertOk();
        $dom = $this->dom($response->getContent());
        $this->assertSame('Gemeinde Merching', $dom->evaluate('string(//title)'));
        $this->assertSame('Gemeinde Merching', $this->meta($dom, 'og:title'));
        $this->assertSame('Gemeinde Merching', $this->meta($dom, 'og:site_name'));
        $this->assertSame('website', $this->meta($dom, 'og:type'));
        $this->assertSame('de_DE', $this->meta($dom, 'og:locale'));
        $this->assertSame('https://www.gemeinde-merching.de/', $this->meta($dom, 'og:url'));
        $this->assertCount(1, $dom->query('//link[@rel="canonical"]'));
        $this->assertSame($this->meta($dom, 'og:url'), $dom->evaluate('string(//link[@rel="canonical"]/@href)'));
        $this->assertSame($this->meta($dom, 'description'), $this->meta($dom, 'og:description'));
        $this->assertSame('https://www.gemeinde-merching.de/identity/social-default.png', $this->meta($dom, 'og:image'));
        $this->assertSame('1200', $this->meta($dom, 'og:image:width'));
        $this->assertSame('630', $this->meta($dom, 'og:image:height'));
        $this->assertSame('image/png', $this->meta($dom, 'og:image:type'));
        $this->assertSame($this->meta($dom, 'og:image'), $this->meta($dom, 'og:image:secure_url'));
        $this->assertSame('summary_large_image', $this->meta($dom, 'twitter:card'));
        $this->assertSame($this->meta($dom, 'og:image'), $this->meta($dom, 'twitter:image'));
        $this->assertSame($this->meta($dom, 'og:title'), $this->meta($dom, 'twitter:title'));
        $this->assertNotSame('', $this->meta($dom, 'twitter:image:alt'));
        $this->assertSame('noindex, nofollow', $this->meta($dom, 'robots'));
        $this->assertSame([], $response->headers->getCookies());
        foreach (['GovernmentOrganization', 'WebSite', 'WebPage', 'ImageObject'] as $type) {
            $this->assertCount(1, $dom->query('//*[@itemtype="https://schema.org/'.$type.'"]'));
        }
        $this->assertCount(0, $dom->query('//script[not(@src)]'));
    }

    public function test_titles_and_descriptions_are_specific_and_editor_overrides_are_escaped(): void
    {
        SiteSettings::create(['municipality_name' => 'Gemeinde Merching', 'default_seo_title' => 'Willkommen', 'default_meta_description' => 'Informationen aus Merching']);
        $this->get('/')->assertSee('<title>Willkommen – Gemeinde Merching</title>', false)->assertSee('content="Informationen aus Merching"', false);
        $page = $this->page(['title' => 'Öffentliche Seite', 'summary' => '**Eigene** Zusammenfassung.']);
        app(RouteManager::class)->assign($page, '/oeffentliche-seite');
        $dom = $this->dom($this->get('/oeffentliche-seite?tracking=ignored')->assertOk()->getContent());
        $this->assertSame('Öffentliche Seite – Gemeinde Merching', $this->meta($dom, 'og:title'));
        $this->assertSame('Eigene Zusammenfassung.', $this->meta($dom, 'description'));
        $this->assertSame('https://www.gemeinde-merching.de/oeffentliche-seite', $this->meta($dom, 'og:url'));
        $page->forceFill(['seo_title' => 'Titel " <Test>', 'meta_description' => 'Beschreibung " <Test>'])->save();
        $response = $this->get('/oeffentliche-seite')->assertOk();
        $dom = $this->dom($response->getContent());
        $this->assertSame('Titel " <Test> – Gemeinde Merching', $this->meta($dom, 'og:title'));
        $this->assertSame('Beschreibung " <Test>', $this->meta($dom, 'description'));
        $response->assertDontSee('<Test>', false);
        $this->get('/kontakt')->assertSee('<title>Kontakt – Gemeinde Merching</title>', false);
    }

    public function test_article_uses_public_media_with_exact_dimensions_and_article_schema(): void
    {
        $article = $this->article(['title' => 'Rathausmeldung', 'summary' => 'Aktuelles aus dem Rathaus.'], PublicationStatus::Published, now()->subDay());
        app(RouteManager::class)->assign($article, '/rathausmeldung');
        $private = $this->medium(false);
        $media = $this->medium();
        $article->media()->attach([$private->id => ['sort_order' => 0], $media->id => ['sort_order' => 1]]);
        $response = $this->get('/rathausmeldung')->assertOk();
        $dom = $this->dom($response->getContent());
        $this->assertSame('article', $this->meta($dom, 'og:type'));
        $this->assertSame('https://www.gemeinde-merching.de/medien/'.$media->id, $this->meta($dom, 'og:image'));
        $this->assertSame('1600', $this->meta($dom, 'og:image:width'));
        $this->assertSame('900', $this->meta($dom, 'og:image:height'));
        $this->assertSame('image/jpeg', $this->meta($dom, 'og:image:type'));
        $this->assertSame('Blick auf das Rathaus', $this->meta($dom, 'og:image:alt'));
        $this->assertCount(1, $dom->query('//*[@itemtype="https://schema.org/NewsArticle"]'));
        $this->assertCount(1, $dom->query('//meta[@itemprop="datePublished"]'));
        $this->get('/medien/'.$media->id)->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->get('/medien/'.$private->id)->assertNotFound();
        $media->forceFill(['status' => PublicationStatus::Draft])->save();
        $dom = $this->dom($this->get('/rathausmeldung')->assertOk()->getContent());
        $this->assertStringEndsWith('/identity/social-default.png', $this->meta($dom, 'og:image'));
        $media->forceFill(['status' => PublicationStatus::Published])->save();
        Storage::disk((string) config('uploads.disk'))->delete($media->file_path);
        $dom = $this->dom($this->get('/rathausmeldung')->assertOk()->getContent());
        $this->assertStringEndsWith('/identity/social-default.png', $this->meta($dom, 'og:image'));
    }

    public function test_events_and_services_have_specific_descriptions_and_truthful_schema(): void
    {
        $event = new Event(['title' => 'Gemeindetermin', 'description' => '**Öffentliche** Veranstaltung.', 'starts_at' => CarbonImmutable::parse('2026-10-31T18:00:00Z'), 'ends_at' => CarbonImmutable::parse('2026-10-31T20:00:00Z'), 'operational_status' => EventOperationalStatus::Cancelled]);
        $event->forceFill(['status' => PublicationStatus::Published, 'publish_at' => now()->subDay()])->save();
        $privateVenue = Location::create(['name' => 'Privater Ort', 'is_active' => false, 'type' => 'sonstige']);
        $event->update(['location_id' => $privateVenue->id]);
        app(RouteManager::class)->assign($event, '/gemeindetermin');
        $response = $this->get('/gemeindetermin')->assertOk()->assertDontSee('Privater Ort');
        $dom = $this->dom($response->getContent());
        $this->assertSame('Öffentliche Veranstaltung.', $this->meta($dom, 'description'));
        $this->assertCount(1, $dom->query('//*[@itemtype="https://schema.org/Event"]'));
        $this->assertSame('2026-10-31T19:00:00+01:00', $dom->evaluate('string(//meta[@itemprop="startDate"]/@content)'));
        $this->assertSame('https://schema.org/EventCancelled', $dom->evaluate('string(//link[@itemprop="eventStatus"]/@href)'));
        $service = new Service(['title' => 'Wohnsitz anmelden', 'summary' => 'Informationen zur Anmeldung.']);
        $service->forceFill(['status' => PublicationStatus::Published, 'publish_at' => now()->subDay()])->save();
        app(RouteManager::class)->assign($service, '/wohnungsanmeldung');
        $dom = $this->dom($this->get('/wohnungsanmeldung')->assertOk()->getContent());
        $this->assertSame('Informationen zur Anmeldung.', $this->meta($dom, 'description'));
        $this->assertCount(1, $dom->query('//*[@itemtype="https://schema.org/GovernmentService"]'));
        $this->assertSame('Wohnsitz anmelden – Gemeinde Merching', $this->meta($dom, 'og:title'));
    }

    public function test_gallery_and_other_page_types_get_images_or_specific_fallbacks(): void
    {
        $gallery = new Gallery(['title' => 'Gemeindebilder', 'description' => 'Bilder aus Merching.']);
        $gallery->forceFill(['status' => PublicationStatus::Published, 'publish_at' => now()->subDay()])->save();
        app(RouteManager::class)->assign($gallery, '/gemeindebilder');
        $media = $this->medium();
        $gallery->items()->create(['media_id' => $media->id, 'sort_order' => 0]);
        $dom = $this->dom($this->get('/gemeindebilder')->assertOk()->getContent());
        $this->assertStringEndsWith('/medien/'.$media->id, $this->meta($dom, 'og:image'));
        foreach (['/buergerservice', '/aktuelles', '/veranstaltungen', '/dokumente', '/verzeichnisse'] as $path) {
            $dom = $this->dom($this->get($path)->assertOk()->getContent());
            $this->assertNotSame(config('seo.default_description'), $this->meta($dom, 'description'));
            $this->assertSame('https://www.gemeinde-merching.de'.$path, $this->meta($dom, 'og:url'));
        }
    }

    public function test_drafts_admin_and_errors_never_emit_public_share_metadata(): void
    {
        $page = $this->page(['title' => 'Geheime Seite'], PublicationStatus::Draft);
        app(RouteManager::class)->assign($page, '/geheime-seite');
        foreach (['/geheime-seite', '/unbekannt', '/verwaltung/login'] as $path) {
            $response = $this->get($path);
            $dom = $this->dom($response->getContent());
            $this->assertSame('noindex, nofollow', $this->meta($dom, 'robots'));
            $this->assertCount(0, $dom->query('//meta[starts-with(@property,"og:")] | //link[@rel="canonical"] | //*[@itemscope]'));
        }
    }

    public function test_sitemap_lists_real_fallback_pages_without_resurrecting_private_managed_routes(): void
    {
        $private = $this->page(['title' => 'Privater Bereich'], PublicationStatus::Draft);
        app(RouteManager::class)->assign($private, '/aktuelles');
        $xml = $this->get('/sitemap.xml')->assertOk()->assertDontSee('https://www.gemeinde-merching.de/aktuelles</loc>', false)
            ->assertSee('https://www.gemeinde-merching.de/buergerservice</loc>', false)->getContent();
        $this->assertStringNotContainsString('preview.test', $xml);
        $this->assertStringNotContainsString('/verwaltung', $xml);
        $this->app['env'] = 'production';
        config(['site.public_indexing' => true]);
        $this->get('/robots.txt')->assertSee('Sitemap: https://www.gemeinde-merching.de/sitemap.xml', false);
    }

    public function test_committed_branding_assets_and_manifest_have_correct_sizes_and_local_paths(): void
    {
        foreach (['favicon-16' => 16, 'favicon-32' => 32, 'favicon-48' => 48, 'apple-touch-icon' => 180, 'icon-192' => 192, 'icon-512' => 512, 'icon-maskable-512' => 512] as $name => $size) {
            $image = getimagesize(public_path('identity/'.$name.'.png'));
            $this->assertSame([$size, $size], [$image[0], $image[1]]);
        }
        $image = getimagesize(public_path('identity/social-default.png'));
        $this->assertSame([1200, 630], [$image[0], $image[1]]);
        $this->assertFileExists(public_path('favicon.ico'));
        $mask = simplexml_load_file(public_path('identity/safari-pinned-tab.svg'));
        $this->assertSame('0 0 16 16', (string) $mask['viewBox']);
        $this->assertCount(1, $mask->children());
        $this->assertSame('#000', (string) $mask->path['fill']);
        $manifest = json_decode(file_get_contents(public_path('site.webmanifest')), true, flags: JSON_THROW_ON_ERROR);
        foreach ($manifest['icons'] as $icon) {
            $this->assertStringStartsWith('/identity/', $icon['src']);
            $this->assertFileExists(public_path($icon['src']));
        }
        foreach (['/identity', '/favicon.ico', '/site.webmanifest'] as $path) {
            $this->assertTrue(PublicPath::isReserved($path));
        }
    }
}
