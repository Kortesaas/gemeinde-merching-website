<?php

namespace Tests\Feature\Content;

use App\Contracts\Routable;
use App\Logging\RedactSensitiveData;
use App\Models\AuditEvent;
use App\Models\ContactRoute;
use App\Support\Authorization\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ContactRoutePrivacyTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'meldeamt-intern@example.test';

    private function route(): ContactRoute
    {
        return ContactRoute::create(['label' => 'Meldewesen', 'explanation' => 'An- und Ummeldung', 'recipients' => [self::SECRET], 'is_active' => true]);
    }

    public function test_recipients_are_encrypted_at_rest(): void
    {
        $route = $this->route();

        $raw = (string) DB::table('contact_routes')->where('id', $route->id)->value('recipients');
        $this->assertStringNotContainsString(self::SECRET, $raw);
        $this->assertStringNotContainsString('example.test', $raw);
        $this->assertSame([self::SECRET], $route->fresh()?->recipients);
    }

    public function test_recipients_never_appear_in_serialisation_or_public_data(): void
    {
        $route = $this->route();

        $this->assertStringNotContainsString(self::SECRET, $route->toJson());
        $this->assertArrayNotHasKey('recipients', $route->toArray());
        $this->assertSame(['id', 'label', 'explanation'], array_keys($route->publicData()));
        $this->assertStringNotContainsString(self::SECRET, (string) json_encode(ContactRoute::query()->get()));
    }

    public function test_recipients_are_not_in_audit_or_revisions(): void
    {
        $admin = $this->createUser();
        $this->actingAsAdmin($admin)->post($this->adminUrl('kontakt-themen'), ['label' => 'Bauamt', 'recipients' => self::SECRET, 'is_active' => '1'])
            ->assertSessionHasNoErrors();

        foreach (AuditEvent::all() as $event) {
            $this->assertStringNotContainsString(self::SECRET, (string) json_encode($event->toArray()));
        }
        $this->assertDatabaseCount('content_revisions', 0);
        $this->assertFalse(method_exists(ContactRoute::class, 'revisionAttributes'));
    }

    public function test_list_and_read_only_views_never_render_recipients(): void
    {
        $route = $this->route();
        $reviewer = $this->createUser(Role::Reviewer);
        $admin = $this->createUser();

        $this->actingAsAdmin($admin)->get($this->adminUrl('kontakt-themen'))->assertOk()->assertSee('Meldewesen')->assertDontSee(self::SECRET);
        $this->actingAsAdmin($reviewer)->get($this->adminUrl('kontakt-themen/'.$route->id))->assertOk()->assertDontSee(self::SECRET);

        // Only users with contact-route.edit see them, in the edit form.
        $this->actingAsAdmin($admin)->get($this->adminUrl('kontakt-themen/'.$route->id))->assertOk()->assertSee(self::SECRET);
    }

    public function test_viewing_requires_backend_permission(): void
    {
        $route = $this->route();
        $events = $this->createUser(Role::Veranstaltungsredaktion);

        $this->actingAsAdmin($events)->get($this->adminUrl('kontakt-themen'))->assertForbidden();
        $this->actingAsAdmin($events)->get($this->adminUrl('kontakt-themen/'.$route->id))->assertForbidden();
        Auth::forgetUser();
        $this->get($this->adminUrl('kontakt-themen/'.$route->id))->assertRedirect($this->adminUrl('login'));
    }

    public function test_no_public_url_exposes_contact_routes(): void
    {
        $this->route();

        $this->get('/kontakt')->assertNotFound()->assertDontSee(self::SECRET);
        $this->assertFalse(is_subclass_of(ContactRoute::class, Routable::class));
    }

    public function test_recipient_values_are_redacted_from_log_context(): void
    {
        $this->assertSame(['recipients' => '[redacted]'], RedactSensitiveData::redact(['recipients' => [self::SECRET]]));
    }

    public function test_invalid_recipient_addresses_are_rejected(): void
    {
        $this->actingAsAdmin($this->createUser())->post($this->adminUrl('kontakt-themen'), ['label' => 'X', 'recipients' => "gut@example.test\nkeine-adresse"])
            ->assertSessionHasErrors('recipients');
        $this->assertSame(0, ContactRoute::count());
    }
}
