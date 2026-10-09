<?php

namespace Tests\Feature\Public;

use App\Enums\PublicationStatus;
use App\Models\Media;
use App\Models\SiteSettings;
use App\Services\Content\ContentUsage;
use App\Services\Content\MediaStorage;
use App\Services\Routing\RouteManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesContent;
use Tests\TestCase;

/** The optional homepage greeting comes only from site settings. */
class HomepageGreetingTest extends TestCase
{
    use CreatesContent, RefreshDatabase;

    public function test_greeting_is_absent_until_an_editor_fills_it(): void
    {
        SiteSettings::create(['municipality_name' => 'Gemeinde Testdorf']);
        $this->get('/')->assertOk()->assertDontSee('class="section greeting', false)->assertDontSee('Zum Grußwort');
    }

    public function test_configured_greeting_shows_quote_person_link_and_image(): void
    {
        Storage::fake((string) config('uploads.disk'));
        $medium = new Media(['title' => 'Grußwortbild', 'alt_text' => 'Neutrale Silhouette']);
        app(MediaStorage::class)->attach($medium, UploadedFile::fake()->image('gruss.jpg', 400, 500));
        $medium->forceFill(['status' => PublicationStatus::Published, 'publish_at' => now()->subDay()])->save();
        $page = $this->page(['title' => 'Grußwort']);
        app(RouteManager::class)->assign($page, '/grusswort');
        SiteSettings::create([
            'municipality_name' => 'Gemeinde Testdorf', 'greeting_text' => 'Herzlich willkommen auf unserer Website.',
            'greeting_name' => 'Erika Beispiel', 'greeting_role' => 'Erste Bürgermeisterin', 'greeting_page_id' => $page->id, 'greeting_media_id' => $medium->id,
        ]);

        $response = $this->get('/')->assertOk()
            ->assertSee('Herzlich willkommen auf unserer Website.')
            ->assertSee('Erika Beispiel')->assertSee('Erste Bürgermeisterin')
            ->assertSee('href="/grusswort"', false)->assertSee('alt="Neutrale Silhouette"', false);
        $this->assertSame([], $response->headers->getCookies());
        $this->get('/medien/'.$medium->id)->assertOk();
        $this->assertTrue(app(ContentUsage::class)->isUsed($medium));

        // A draft page is not linked; the quote itself stays.
        $page->forceFill(['status' => PublicationStatus::Draft])->save();
        $this->get('/')->assertSee('Erika Beispiel')->assertDontSee('Zum Grußwort');
    }

    public function test_greeting_preserves_formatted_salutation_and_paragraphs_safely(): void
    {
        SiteSettings::create([
            'municipality_name' => 'Gemeinde Testdorf',
            'greeting_text' => "***Liebe Mitbürgerinnen, liebe Mitbürger,***\\\n***liebe Besucher,***\n\nWillkommen in unserer Gemeinde. <script>alert(1)</script>",
            'greeting_name' => 'Erika Beispiel',
        ]);

        $this->get('/')->assertOk()
            ->assertSee('<em><strong>Liebe Mitbürgerinnen, liebe Mitbürger,</strong></em><br', false)
            ->assertSee('<em><strong>liebe Besucher,</strong></em>', false)
            ->assertSee('<p>Willkommen in unserer Gemeinde.', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }
}
