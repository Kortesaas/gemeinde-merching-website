<?php

namespace Tests\Feature\Public;

use App\Mail\ContactMessage;
use App\Models\ContactRoute;
use App\Services\Mail\ContactDelivery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ContactFormTest extends TestCase
{
    use RefreshDatabase;

    private function topic(): ContactRoute
    {
        return ContactRoute::create(['label' => 'Meldewesen', 'recipients' => ['private@example.test'], 'is_active' => true]);
    }

    private function ready(ContactRoute $topic, array $extra = []): array
    {
        $this->get('/kontakt')->assertOk();
        $nonce = session('contact.nonce');
        $this->travel(4)->seconds();

        return ['contact_route_id' => $topic->id, 'contact_name' => 'Testperson', 'contact_email' => 'visitor@example.test', 'contact_message' => 'Eine synthetische Testanfrage.', 'form_nonce' => $nonce, ...$extra];
    }

    public function test_form_only_exposes_active_topics_and_never_recipient_address(): void
    {
        $this->topic();
        ContactRoute::create(['label' => 'Inaktiv', 'recipients' => ['secret@example.test'], 'is_active' => false]);
        $r = $this->get('/kontakt')->assertOk()->assertSee('Meldewesen')->assertDontSee('private@example.test')->assertDontSee('secret@example.test')->assertDontSee('Inaktiv')->assertSee('name="_token"', false)->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->assertStringContainsString('no-store', $r->headers->get('Cache-Control'));
        $this->assertNotEmpty($r->headers->getCookies());
        $this->assertSame([], $this->get('/')->headers->getCookies());
    }

    public function test_routing_and_reply_to_are_correct_with_minimal_safe_audit(): void
    {
        Mail::fake();
        $topic = $this->topic();
        $payload = $this->ready($topic, ['contact_phone' => '+49 123']);
        $this->post('/kontakt', $payload)->assertRedirect(route('public.contact.success'));
        Mail::assertSent(ContactMessage::class, fn ($mail) => $mail->hasTo('private@example.test') && $mail->envelope()->replyTo[0]->address === 'visitor@example.test' && $mail->enquiry['contact_phone'] === '+49 123');
        $event = DB::table('audit_events')->where('action', 'contact.sent')->first();
        $this->assertNotNull($event);
        $this->assertSame(['topic_id' => $topic->id], json_decode($event->metadata, true));
        $this->assertStringNotContainsString('Testperson', json_encode(DB::table('audit_events')->get()));
        $this->assertNull(session('contact'));
        $this->get('/kontakt/bestaetigung')->assertOk()->assertSee('Ihre Anfrage wurde übermittelt');
    }

    public function test_optional_phone_and_no_permanent_contact_message_table(): void
    {
        Mail::fake();
        $this->post('/kontakt', $this->ready($this->topic()))->assertRedirect(route('public.contact.success'));
        Mail::assertSentCount(1);
        $this->assertFalse(Schema::hasTable('contact_messages'));
    }

    public function test_invalid_fields_have_accessible_errors_and_no_sensitive_old_input(): void
    {
        Mail::fake();
        $payload = $this->ready($this->topic(), ['contact_name' => '', 'contact_email' => 'invalid', 'contact_message' => 'x']);
        $this->from('/kontakt')->post('/kontakt', $payload)->assertSessionHasErrors(['contact_name', 'contact_email', 'contact_message']);
        $this->assertArrayNotHasKey('contact_message', session()->getOldInput());
        $this->assertArrayNotHasKey('contact_email', session()->getOldInput());
        $this->withSessionCookie()->get('/kontakt')->assertSee('Bitte prüfen Sie Ihre Angaben')->assertSee('aria-invalid="true"', false)->assertSee('<title>Fehler:', false);
        Mail::assertNothingSent();
    }

    public function test_header_injection_is_rejected(): void
    {
        Mail::fake();
        $this->post('/kontakt', $this->ready($this->topic(), ['contact_name' => "Test\r\nBcc: other@example.test"]))->assertSessionHasErrors('contact_name');
        Mail::assertNothingSent();
    }

    public function test_inactive_and_deleted_topics_cannot_be_submitted(): void
    {
        $topic = $this->topic();
        $payload = $this->ready($topic);
        $topic->update(['is_active' => false]);
        $this->post('/kontakt', $payload)->assertSessionHasErrors('contact_route_id');
        $topic->update(['is_active' => true]);
        $topic->delete();
        $this->post('/kontakt', $payload)->assertSessionHasErrors('contact_route_id');
    }

    public function test_honeypot_discards_message_without_delivery_or_audit_body(): void
    {
        Mail::fake();
        $this->post('/kontakt', $this->ready($this->topic(), ['website' => 'spam']))->assertRedirect(route('public.contact.success'));
        Mail::assertNothingSent();
        $this->assertDatabaseCount('audit_events', 0);
    }

    public function test_minimum_time_and_expiry_are_enforced(): void
    {
        Mail::fake();
        $topic = $this->topic();
        $payload = $this->ready($topic);
        $this->travelBack();
        $this->post('/kontakt', $payload)->assertSessionHasErrors('general');
        $this->travel(2)->hours();
        $this->post('/kontakt', $payload)->assertSessionHasErrors('general');
        Mail::assertNothingSent();
    }

    public function test_single_use_token_rejects_replay(): void
    {
        Mail::fake();
        $payload = $this->ready($this->topic());
        $this->post('/kontakt', $payload)->assertRedirect(route('public.contact.success'));
        $this->post('/kontakt', $payload)->assertSessionHasErrors('general');
        Mail::assertSentCount(1);
    }

    public function test_too_many_links_are_rejected(): void
    {
        Mail::fake();
        $this->post('/kontakt', $this->ready($this->topic(), ['contact_message' => str_repeat('https://example.test ', 6)]))->assertSessionHasErrors('contact_message');
        Mail::assertNothingSent();
    }

    public function test_rate_limit_is_applied_even_to_invalid_requests(): void
    {
        config(['contact.hourly_limit' => 2]);
        $this->post('/kontakt', [])->assertRedirect();
        $this->post('/kontakt', [])->assertRedirect();
        $this->post('/kontakt', [])->assertStatus(429);
    }

    public function test_post_requires_csrf_and_rejects_cross_site_requests(): void
    {
        $this->app['env'] = 'local';
        $this->post('/kontakt', [])->assertStatus(419);
        $this->withHeader('Sec-Fetch-Site', 'cross-site')->post('/kontakt', [])->assertStatus(419);
    }

    public function test_valid_csrf_token_reaches_validation(): void
    {
        $this->app['env'] = 'local';
        $this->get('/kontakt');
        $this->post('/kontakt', ['_token' => session()->token()])->assertSessionHasErrors('contact_name');
    }

    public function test_logging_transport_is_refused_without_leaking_message(): void
    {
        config(['mail.default' => 'log']);
        $payload = $this->ready($this->topic());
        $this->post('/kontakt', $payload)->assertRedirect(route('public.contact'))->assertSessionHasErrors('general');
        $this->assertDatabaseHas('audit_events', ['action' => 'contact.delivery_failed']);
        $this->assertStringNotContainsString('Testperson', json_encode(DB::table('audit_events')->get()));
    }

    public function test_transport_exception_is_not_logged_or_displayed(): void
    {
        $this->mock(ContactDelivery::class)->shouldReceive('send')->once()->andThrow(new \RuntimeException('SECRET body visitor@example.test'));
        $this->post('/kontakt', $this->ready($this->topic()))->assertSessionHasErrors('general');
        $this->assertStringNotContainsString('SECRET', json_encode(session()->all()));
    }

    public function test_array_transport_contains_original_plain_text_without_html_encoding(): void
    {
        $topic = $this->topic();
        app(ContactDelivery::class)->send($topic, ['contact_name' => 'Test', 'contact_email' => 'visitor@example.test', 'contact_message' => 'Text mit <Test> & Sonderzeichen.']);
        $sent = Mail::mailer()->getSymfonyTransport()->messages()->first();
        $this->assertSame('Anfrage über das Kontaktformular', $sent->getOriginalMessage()->getSubject());
        $this->assertStringContainsString('Text mit <Test> & Sonderzeichen.', $sent->getOriginalMessage()->getTextBody());
    }
}
