<?php

namespace Tests\Feature\Content;

use App\Admin\Resources\CommitteeResource;
use App\Admin\Resources\CouncilTermResource;
use App\Admin\Resources\EventResource;
use App\Admin\Resources\ServiceResource;
use App\Enums\EventOperationalStatus;
use App\Enums\LocationType;
use App\Enums\PublicationStatus;
use App\Models\CouncilMember;
use App\Models\CouncilTerm;
use App\Models\ExternalResource;
use App\Models\Location;
use App\Models\Service;
use App\Services\Content\ProposalService;
use App\Services\Content\ReferenceProtection;
use App\Services\Content\RevisionService;
use App\Services\Routing\RouteManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesContent;
use Tests\TestCase;

class EditorialParityTest extends TestCase
{
    use CreatesContent, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake((string) config('uploads.disk'));
    }

    public function test_expired_and_superseded_public_archive_documents_remain_downloadable(): void
    {
        $old = $this->document(['title' => 'Historisches Testdokument', 'valid_until' => '2025-12-31']);
        $old->forceFill(['expires_at' => now()->subDay()])->save();
        $this->assertFalse($old->isVisible());
        $this->assertTrue($old->isInPublicArchive());
        $this->assertTrue($old->isPubliclyReachable());
        $this->get($old->downloadPath())->assertOk()->assertStreamedContent(self::PDF);

        $replacement = $this->document(['title' => 'Neue Fassung', 'replaces_document_id' => $old->id]);
        $old->forceFill(['status' => PublicationStatus::Archived, 'archived_at' => now()])->save();
        $this->assertTrue($old->isSuperseded());
        app(RouteManager::class)->assign($old, '/archiv/alte-fassung.pdf');
        $response = $this->get('/archiv/alte-fassung.pdf')->assertOk()->assertStreamedContent(self::PDF);
        $this->assertSame([], $response->headers->getCookies());
        $page = $this->page(['title' => 'Dokumentarchiv']);
        app(RouteManager::class)->assign($page, '/dokumentarchiv');
        $page->documents()->attach($old->id, ['slot' => 'downloads', 'sort_order' => 0]);
        $this->get('/dokumentarchiv')->assertSee('/archiv/alte-fassung.pdf')->assertSee($old->title);
        $this->get($replacement->downloadPath())->assertOk();
    }

    public function test_draft_and_never_published_archive_documents_are_not_public(): void
    {
        $draft = $this->document([], PublicationStatus::Draft);
        $draft->forceFill(['expires_at' => now()->subDay()])->save();
        $this->get($draft->downloadPath())->assertNotFound();
        $draft->forceFill(['status' => PublicationStatus::Archived, 'publish_at' => null])->save();
        $this->assertFalse($draft->isPubliclyReachable());
        $this->get($draft->downloadPath())->assertNotFound();
    }

    public function test_service_details_and_multiple_fee_rows_render_and_restore(): void
    {
        $admin = $this->createUser();
        $resource = app(ServiceResource::class);
        $service = $resource->save(null, [
            'title' => 'Testleistung', 'prerequisites' => 'Voraussetzung A', 'required_items' => 'Nachweis B', 'processing_duration' => 'Nach Prüfung', 'important_notice' => 'Bitte vorher anfragen.', 'online_service_mode' => 'unavailable',
            'fees' => [['description' => 'Variante A', 'context' => 'Regelfall', 'amount' => '12.50', 'note' => 'Testgebühr', 'sort_order' => 10], ['description' => 'Variante B', 'amount' => null, 'note' => 'Nach Aufwand', 'sort_order' => 20]],
        ], Request::create('/'), $admin);
        $first = $service->revisions()->firstOrFail();
        $this->assertCount(2, $first->snapshot['collections']['fees']);
        $resource->save($service, ['prerequisites' => 'Geändert', 'fees' => [['description' => 'Neu', 'amount' => 20, 'sort_order' => 0]]], Request::create('/'), $admin);
        app(RevisionService::class)->restore($first, $admin);
        $this->assertSame('Voraussetzung A', $service->fresh()->prerequisites);
        $this->assertSame(['Variante A', 'Variante B'], $service->fees()->pluck('description')->all());
        $resource->save($service, ['status' => 'published', 'publish_at' => '2026-01-01T10:00'], Request::create('/'), $admin);
        $this->get($service->fresh()->publicPath())->assertOk()->assertSee('12,50 EUR')->assertSee('Nach Aufwand')->assertSee('Voraussetzungen')->assertSee('Nachweis B')->assertSee('<caption>Gebühren</caption>', false)->assertSee('Kein Online-Dienst');
    }

    public function test_service_detail_proposal_includes_fees_and_preserves_live_values(): void
    {
        $admin = $this->createUser();
        $author = $this->userWithPermissions(['service.edit']);
        $service = Service::create(['title' => 'Testleistung', 'prerequisites' => 'Bisher']);
        $service->forceFill(['status' => PublicationStatus::Published, 'publish_at' => now()->subDay()])->save();
        $proposals = app(ProposalService::class);
        $proposal = $proposals->create($service, $author);
        $proposals->update($proposal, app(ServiceResource::class), ['prerequisites' => 'Neu', 'fees' => [['description' => 'Testgebühr', 'amount' => 5, 'sort_order' => 0]]], Request::create('/'), $author);
        $this->assertSame('Bisher', $service->fresh()->prerequisites);
        $this->assertSame(0, $service->fees()->count());
        $proposals->submit($proposal, $author);
        $proposals->apply($proposal, $admin, false);
        $this->assertSame('Neu', $service->fresh()->prerequisites);
        $this->assertSame('5.00', $service->fees()->firstOrFail()->amount);
    }

    public function test_invalid_fee_or_online_mode_cannot_publish(): void
    {
        $this->actingAsAdmin($this->createUser())->post($this->adminUrl('buergerservice'), ['title' => 'Test', 'fees' => [['description' => 'Gebühr', 'amount' => -1, 'sort_order' => 0]]])->assertSessionHasErrors('fees');
        $this->post($this->adminUrl('buergerservice'), ['title' => 'Online', 'online_service_mode' => 'application', 'status' => 'published', 'publish_at' => '2026-01-01T10:00'])->assertSessionHasErrors('online_service_resource_id');
        $this->assertDatabaseCount('services', 0);
    }

    public function test_online_service_uses_managed_resource_without_automatic_external_requests(): void
    {
        $link = ExternalResource::create(['title' => 'Synthetischer Online-Dienst', 'url' => 'https://example.test/antrag', 'type' => 'online_service', 'privacy_note' => 'Externer Anbieter']);
        $link->forceFill(['status' => PublicationStatus::Published, 'publish_at' => now()->subDay()])->save();
        $this->actingAsAdmin($this->createUser())->post($this->adminUrl('buergerservice'), ['title' => 'Online', 'online_service_mode' => 'application', 'online_service_resource_id' => $link->id, 'status' => 'published', 'publish_at' => '2026-01-01T10:00'])->assertSessionHasNoErrors();
        $service = Service::query()->firstOrFail();
        $this->get($service->publicPath())->assertOk()->assertSee('href="https://example.test/antrag"', false)->assertSee('Externer Anbieter')->assertDontSee('<iframe', false);
    }

    public function test_cancelled_event_stays_visible_and_reschedule_notice_is_revisioned(): void
    {
        $admin = $this->createUser();
        $resource = app(EventResource::class);
        $event = $resource->save(null, ['title' => 'Testtermin', 'starts_at' => '2026-10-22T18:00', 'operational_status' => 'scheduled', 'status' => 'published', 'publish_at' => '2026-01-01T10:00'], Request::create('/'), $admin);
        $first = $event->revisions()->firstOrFail();
        $resource->save($event, ['operational_status' => 'cancelled', 'schedule_notice' => 'Termin wurde verschoben – neuer Termin nach Abstimmung.'], Request::create('/'), $admin);
        $this->assertTrue($event->fresh()->isPubliclyReachable());
        $this->get($event->fresh()->publicPath())->assertOk()->assertSee('Abgesagt')->assertSee('Termin wurde verschoben');
        app(RevisionService::class)->restore($first, $admin);
        $this->assertSame(EventOperationalStatus::Scheduled, $event->fresh()->operational_status);
        $this->assertNull($event->fresh()->schedule_notice);
    }

    public function test_event_date_and_cancellation_changes_can_be_proposed_together(): void
    {
        $admin = $this->createUser();
        $author = $this->userWithPermissions(['event.edit']);
        $resource = app(EventResource::class);
        $event = $resource->save(null, ['title' => 'Test', 'starts_at' => '2026-10-22T18:00', 'status' => 'published', 'publish_at' => '2026-01-01T10:00'], Request::create('/'), $admin);
        $old = $event->starts_at;
        $proposal = app(ProposalService::class)->create($event, $author);
        app(ProposalService::class)->update($proposal, $resource, ['starts_at' => '2026-10-23T18:00', 'operational_status' => 'cancelled', 'schedule_notice' => 'Neuer Termin wird bekannt gegeben.'], Request::create('/'), $author);
        $this->assertTrue($event->fresh()->starts_at->equalTo($old));
        $this->assertSame(EventOperationalStatus::Scheduled, $event->fresh()->operational_status);
        app(ProposalService::class)->submit($proposal, $author);
        app(ProposalService::class)->apply($proposal, $admin, false);
        $this->assertSame('2026-10-23', $event->fresh()->starts_at->format('Y-m-d'));
        $this->assertSame(EventOperationalStatus::Cancelled, $event->fresh()->operational_status);
    }

    public function test_location_accessibility_is_unknown_by_default_and_public_when_entered(): void
    {
        $location = Location::create(['name' => 'Testort', 'type' => LocationType::Venue, 'is_active' => true]);
        $this->assertNull($location->fresh()->accessibility_note);
        app(RouteManager::class)->assign($location, '/testort');
        $this->get('/testort')->assertOk()->assertDontSee('Zugänglichkeit:');
        $admin = $this->createUser();
        $this->actingAsAdmin($admin)->put($this->adminUrl('orte/'.$location->id), ['name' => 'Testort', 'type' => 'veranstaltungsort', 'is_active' => 1, 'accessibility_note' => 'Stufenlos erreichbar; Aufzug vorhanden.'])->assertSessionHasNoErrors();
        $this->get('/testort')->assertOk()->assertSee('Stufenlos erreichbar; Aufzug vorhanden.');
        $revision = $location->revisions()->firstOrFail();
        $this->assertSame('Stufenlos erreichbar; Aufzug vorhanden.', $revision->snapshot['attributes']['accessibility_note']);
    }

    public function test_location_map_block_only_links_to_a_managed_resource(): void
    {
        $link = ExternalResource::create(['title' => 'Karte', 'url' => 'https://example.test/karte', 'type' => 'map', 'privacy_note' => 'Beim Öffnen wird der Anbieter kontaktiert.']);
        $link->forceFill(['status' => PublicationStatus::Published, 'publish_at' => now()->subDay()])->save();
        $location = Location::create(['name' => 'Testort', 'type' => LocationType::Venue, 'is_active' => true, 'map_resource_id' => $link->id, 'accessibility_note' => 'Eingeschränkt zugänglich']);
        $page = $this->page();
        app(RouteManager::class)->assign($page, '/karteninformation');
        $page->blocks()->create(['type' => 'location', 'location_id' => $location->id, 'sort_order' => 0]);
        $this->get('/karteninformation')->assertOk()->assertSee('href="https://example.test/karte"', false)->assertSee('Eingeschränkt zugänglich')->assertDontSee('<iframe', false);
    }

    private function publishedMember(string $title): CouncilMember
    {
        $member = CouncilMember::create(['title' => $title]);
        $member->forceFill(['status' => PublicationStatus::Published, 'publish_at' => now()->subYear()])->save();

        return $member;
    }

    public function test_council_term_and_committee_publish_relational_memberships_without_employee_records(): void
    {
        $admin = $this->createUser();
        $member = $this->publishedMember('Synthetisches Ratsmitglied');
        $term = app(CouncilTermResource::class)->save(null, ['title' => 'Testwahlperiode', 'starts_on' => '2020-05-01', 'ends_on' => '2026-04-30', 'is_historical' => 1, 'memberships' => [['council_member_id' => $member->id, 'role' => 'Mitglied', 'grouping' => 'Testliste', 'sort_order' => 0]], 'status' => 'published', 'publish_at' => '2020-05-01T10:00', 'public_path' => '/gemeinderat/testperiode'], Request::create('/'), $admin);
        $committee = app(CommitteeResource::class)->save(null, ['title' => 'Testausschuss', 'council_term_id' => $term->id, 'committeeMemberships' => [['council_member_id' => $member->id, 'role' => 'Vorsitz', 'sort_order' => 0]], 'status' => 'published', 'publish_at' => '2020-05-01T10:00'], Request::create('/'), $admin);
        $this->assertDatabaseCount('people', 0);
        $this->get('/gemeinderat/testperiode')->assertOk()->assertSee('Historische Wahlperiode')->assertSee('Synthetisches Ratsmitglied')->assertSee('Testliste')->assertSee('Testausschuss')->assertSee('Vorsitz');
        $this->assertSame($term->id, $committee->term->id);
        $this->assertNotEmpty(app(ReferenceProtection::class)->usages($member));
        $this->assertDatabaseHas('search_entries', ['content_type' => 'wahlperioden', 'content_id' => $term->id]);
    }

    public function test_committee_member_must_belong_to_the_same_term_before_publication(): void
    {
        $member = $this->publishedMember('Testmitglied');
        $term = CouncilTerm::create(['title' => 'Testperiode']);
        $this->actingAsAdmin($this->createUser())->post($this->adminUrl('ausschuesse'), ['title' => 'Testausschuss', 'council_term_id' => $term->id, 'committeeMemberships' => [['council_member_id' => $member->id, 'role' => 'Mitglied', 'sort_order' => 0]], 'status' => 'published', 'publish_at' => '2026-01-01T10:00'])->assertSessionHasErrors('committeeMemberships');
        $this->assertDatabaseCount('committees', 0);
    }

    public function test_historical_term_and_archived_members_remain_public_but_drafts_do_not(): void
    {
        $member = $this->publishedMember('Historisches Mitglied');
        $member->forceFill(['status' => PublicationStatus::Archived, 'archived_at' => now()])->save();
        $private = CouncilMember::create(['title' => 'Privates Mitglied']);
        $term = CouncilTerm::create(['title' => 'Historische Testperiode', 'is_historical' => true]);
        $term->memberships()->create(['council_member_id' => $member->id, 'role' => 'Mitglied', 'sort_order' => 0]);
        $term->memberships()->create(['council_member_id' => $private->id, 'role' => 'Mitglied', 'sort_order' => 1]);
        $term->forceFill(['status' => PublicationStatus::Archived, 'publish_at' => now()->subYears(7), 'archived_at' => now()])->save();
        app(RouteManager::class)->assign($term, '/historischer-rat');
        $this->get('/historischer-rat')->assertOk()->assertSee('Historisches Mitglied')->assertDontSee('Privates Mitglied');
    }

    public function test_council_membership_revision_and_proposal_restore_role_grouping_and_order(): void
    {
        $admin = $this->createUser();
        $member = $this->publishedMember('Testmitglied');
        $resource = app(CouncilTermResource::class);
        $term = $resource->save(null, ['title' => 'Testperiode', 'memberships' => [['council_member_id' => $member->id, 'role' => 'Mitglied', 'grouping' => 'Testliste', 'sort_order' => 0]]], Request::create('/'), $admin);
        $first = $term->revisions()->firstOrFail();
        $resource->save($term, ['memberships' => [['council_member_id' => $member->id, 'role' => 'Vorsitz', 'sort_order' => 0]]], Request::create('/'), $admin);
        app(RevisionService::class)->restore($first, $admin);
        $this->assertSame('Mitglied', $term->memberships()->firstOrFail()->role);
        $resource->save($term, ['status' => 'published', 'publish_at' => '2026-01-01T10:00'], Request::create('/'), $admin);
        $author = $this->userWithPermissions(['wahlperioden.edit']);
        $proposal = app(ProposalService::class)->create($term, $author);
        app(ProposalService::class)->update($proposal, $resource, ['memberships' => [['council_member_id' => $member->id, 'role' => 'Bürgermeister', 'grouping' => 'Testliste', 'sort_order' => 0]]], Request::create('/'), $author);
        $this->assertSame('Mitglied', $term->memberships()->firstOrFail()->role);
        app(ProposalService::class)->submit($proposal, $author);
        app(ProposalService::class)->apply($proposal, $admin, false);
        $this->assertSame('Bürgermeister', $term->memberships()->firstOrFail()->role);
    }

    public function test_council_members_cannot_be_purged_while_referenced_in_history(): void
    {
        $member = $this->publishedMember('Testmitglied');
        $admin = $this->createUser();
        $term = app(CouncilTermResource::class)->save(null, ['title' => 'Testperiode', 'memberships' => [['council_member_id' => $member->id, 'role' => 'Mitglied', 'sort_order' => 0]]], Request::create('/'), $admin);
        $term->memberships()->delete();
        $member->delete();
        $this->actingAsAdmin($admin)->delete($this->adminUrl('ratsmitglieder/'.$member->id.'/endgueltig'))->assertSessionHasErrors('general');
        $this->assertNotNull(CouncilMember::withTrashed()->find($member->id));
    }
}
