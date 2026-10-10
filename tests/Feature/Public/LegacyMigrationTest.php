<?php

namespace Tests\Feature\Public;

use App\Models\Document;
use App\Models\LegacyUrl;
use App\Models\Page;
use App\Services\Routing\RouteManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegacyMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_query_and_slash_aliases_redirect_once_to_public_canonical_content(): void
    {
        $page = Page::create(['title' => 'Verwaltung']);
        $page->forceFill(['status' => 'published', 'publish_at' => now()->subDay()])->save();
        app(RouteManager::class)->assign($page, '/rathaus-und-politik/verwaltung');
        foreach (['/?page_id=20', '/adressen-oeffnungszeiten'] as $url) {
            LegacyUrl::create(['url' => $url, 'url_hash' => hash('sha256', $url), 'target_type' => 'page', 'target_id' => $page->id]);
        }
        $this->get('/?page_id=20')->assertStatus(301)->assertRedirect('/rathaus-und-politik/verwaltung');
        $this->get('/adressen-oeffnungszeiten/')->assertStatus(301)->assertRedirect('/rathaus-und-politik/verwaltung');
        $this->get('/rathaus-und-politik/verwaltung')->assertOk()->assertCookieMissing('laravel_session');
        $page->forceFill(['status' => 'draft'])->save();
        $this->get('/?page_id=20')->assertNotFound();
        $this->get('/adressen-oeffnungszeiten/')->assertNotFound();
    }

    public function test_unknown_private_and_malformed_downloads_do_not_resolve(): void
    {
        $document = new Document(['title' => 'Unveröffentlicht']);
        $document->forceFill(['file_path' => 'private.pdf', 'original_filename' => 'private.pdf', 'mime_type' => 'application/pdf', 'extension' => 'pdf', 'size_bytes' => 1, 'sha256' => str_repeat('a', 64)])->save();
        LegacyUrl::create(['url' => '/?wpdmdl=99', 'url_hash' => hash('sha256', '/?wpdmdl=99'), 'target_type' => 'document', 'target_id' => $document->id]);
        foreach (['/?wpdmdl=99', '/?wpdmdl=100', '/?wpdmdl[]=99', '/?wpdmdl=99&password=secret', '/?p=20&page_id=20', '/wp-content/uploads/private.pdf'] as $url) {
            $this->get($url)->assertNotFound();
        }
    }
}
