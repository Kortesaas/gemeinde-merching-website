<?php

namespace Tests\Feature\Public;

use App\Enums\PublicationStatus;
use App\Mail\ContactMessage;
use App\Models\ContactRoute;
use App\Models\SiteSettings;
use App\Services\Routing\RouteManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\CreatesContent;
use Tests\TestCase;

class ContentFeedbackTest extends TestCase
{
    use CreatesContent, RefreshDatabase;

    public function test_feedback_derives_public_context_and_reuses_secret_recipient_routing(): void
    {
        Mail::fake();
        $topic = ContactRoute::create(['label' => 'Inhaltsredaktion', 'recipients' => ['private-feedback@example.test'], 'is_active' => true]);
        SiteSettings::create(['municipality_name' => 'Testgemeinde', 'central_contact_route_id' => $topic->id]);
        $page = $this->page(['title' => 'Testinformation']);
        app(RouteManager::class)->assign($page, '/testinformation');
        $response = $this->get('/kontakt?feedback=%2Ftestinformation')->assertOk()->assertSee('Fehler melden zu')->assertSee('Testinformation')->assertDontSee('private-feedback@example.test')->assertSee('selected', false)->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $nonce = session('contact.nonce');
        $this->travel(4)->seconds();
        $message = 'Synthetischer Fehlerbericht mit privaten Angaben.';
        $this->post('/kontakt', ['contact_route_id' => $topic->id, 'contact_name' => 'Testperson', 'contact_email' => 'visitor@example.test', 'contact_message' => $message, 'contact_privacy' => '1', 'form_nonce' => $nonce, 'source_url' => 'https://evil.test', 'contact_context' => ['path' => '/fake', 'title' => 'Gefälscht']])->assertRedirect(route('public.contact.success'));
        Mail::assertSent(ContactMessage::class, function ($mail) use ($message) {
            return $mail->hasTo('private-feedback@example.test') && $mail->enquiry['contact_context'] === ['path' => '/testinformation', 'title' => 'Testinformation', 'type' => 'page'] && $mail->enquiry['contact_message'] === $message;
        });
        $audit = json_encode(DB::table('audit_events')->where('action', 'like', 'contact.%')->get());
        $this->assertStringNotContainsString($message, $audit);
        $this->assertStringNotContainsString('visitor@example.test', $audit);
        $this->assertStringNotContainsString('/testinformation', $audit);
        $this->assertNull(session('contact'));
    }

    public function test_external_unpublished_reserved_and_alias_contexts_are_rejected(): void
    {
        $draft = $this->page([], PublicationStatus::Draft);
        app(RouteManager::class)->assign($draft, '/privat');
        foreach (['https://evil.test/quelle', '//evil.test/quelle', '/verwaltung/login', '/privat', '/unbekannt', '/privat?secret=test'] as $path) {
            $this->get('/kontakt?feedback='.urlencode($path))->assertNotFound();
        }
        $page = $this->page();
        app(RouteManager::class)->assign($page, '/alte-seite');
        app(RouteManager::class)->assign($page, '/neue-seite');
        $this->get('/kontakt?feedback=%2Falte-seite')->assertNotFound();
    }

    public function test_regular_contact_cannot_be_given_forged_feedback_context(): void
    {
        Mail::fake();
        $topic = ContactRoute::create(['label' => 'Testthema', 'recipients' => ['private@example.test'], 'is_active' => true]);
        $this->get('/kontakt');
        $nonce = session('contact.nonce');
        $this->travel(4)->seconds();
        $this->post('/kontakt', ['contact_route_id' => $topic->id, 'contact_name' => 'Test', 'contact_email' => 'visitor@example.test', 'contact_message' => 'Synthetische Testnachricht', 'contact_subject' => 'Testanfrage', 'contact_street' => 'Teststraße 1', 'contact_postal_code' => '86504', 'contact_city' => 'Merching', 'contact_reply_by' => 'email', 'contact_privacy' => '1', 'form_nonce' => $nonce, 'feedback' => '/gefälscht'])->assertRedirect(route('public.contact.success'));
        Mail::assertSent(ContactMessage::class, fn ($mail) => $mail->enquiry['contact_context'] === null);
    }

    public function test_context_is_rechecked_if_content_is_withdrawn_after_form_load(): void
    {
        Mail::fake();
        $topic = ContactRoute::create(['label' => 'Testthema', 'recipients' => ['private@example.test'], 'is_active' => true]);
        $page = $this->page();
        app(RouteManager::class)->assign($page, '/testseite');
        $this->get('/kontakt?feedback=%2Ftestseite');
        $nonce = session('contact.nonce');
        $page->delete();
        $this->travel(4)->seconds();
        $this->post('/kontakt', ['contact_route_id' => $topic->id, 'contact_name' => 'Test', 'contact_email' => 'visitor@example.test', 'contact_message' => 'Synthetische Testnachricht', 'contact_privacy' => '1', 'form_nonce' => $nonce])->assertRedirect(route('public.contact.success'));
        Mail::assertSent(ContactMessage::class, fn ($mail) => $mail->enquiry['contact_context'] === null);
    }
}
