<?php

namespace Tests\Feature\Content;

use App\Enums\ProposalStatus;
use App\Enums\PublicationStatus;
use App\Models\Article;
use App\Models\ContentProposal;
use App\Models\Service;
use App\Models\Tag;
use App\Models\User;
use App\Services\Routing\RouteManager;
use App\Support\Authorization\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\Concerns\CreatesContent;
use Tests\TestCase;

class ProposalWorkflowTest extends TestCase
{
    use CreatesContent, RefreshDatabase;

    private User $editor;

    private User $publisher;

    private Article $live;

    protected function setUp(): void
    {
        parent::setUp();
        $this->editor = $this->createUser(Role::Fachbereichsredaktion);
        $this->publisher = $this->createUser(Role::Chefredaktion);
        $this->live = $this->article(['title' => 'Öffnungszeiten', 'body' => 'Montag 8–12 Uhr'], PublicationStatus::Published, now()->subDay());
        app(RouteManager::class)->assign($this->live, '/aktuelles/oeffnungszeiten');
    }

    private function propose(User $author, array $changes, string $action = 'save'): ContentProposal
    {
        $this->actingAsAdmin($author)->post($this->adminUrl('artikel/'.$this->live->id.'/vorschlaege'))->assertRedirect();
        $proposal = ContentProposal::query()->where('author_id', $author->id)->latest('id')->firstOrFail();

        $this->actingAsAdmin($author)->put($this->adminUrl('freigaben/'.$proposal->id), [
            'title' => $this->live->title, 'body' => $this->live->body, ...$changes, 'action' => $action,
        ])->assertSessionHasNoErrors();

        return $proposal->refresh();
    }

    public function test_editor_proposes_without_changing_the_live_version(): void
    {
        $proposal = $this->propose($this->editor, ['body' => 'Montag und Dienstag 8–12 Uhr', 'proposal_summary' => 'Dienstag ergänzt'], 'submit');

        $this->assertSame(ProposalStatus::Submitted, $proposal->status);
        $this->assertSame('Montag 8–12 Uhr', $this->live->fresh()?->body);
        $this->get('/aktuelles/oeffnungszeiten')->assertOk()->assertSee('Montag 8–12 Uhr')->assertDontSee('Dienstag');
        $this->assertDatabaseHas('audit_events', ['action' => 'proposal.submitted', 'subject_id' => $this->live->id]);
    }

    public function test_publisher_approves_and_the_proposal_goes_live_with_history(): void
    {
        $proposal = $this->propose($this->editor, ['body' => 'Montag und Dienstag 8–12 Uhr', 'proposal_summary' => 'Dienstag ergänzt'], 'submit');

        $this->actingAsAdmin($this->publisher)->get($this->adminUrl('freigaben'))->assertOk()->assertSee($proposal->displayTitle());
        $this->actingAsAdmin($this->publisher)->get($this->adminUrl('freigaben/'.$proposal->id))->assertOk()
            ->assertSee('Montag 8–12 Uhr')->assertSee('Montag und Dienstag 8–12 Uhr');

        $this->actingAsAdmin($this->publisher)->post($this->adminUrl('freigaben/'.$proposal->id.'/freigeben'))->assertSessionHasNoErrors();

        $proposal->refresh();
        $this->assertSame(ProposalStatus::Applied, $proposal->status);
        $this->assertSame($this->publisher->id, $proposal->reviewer_id);
        $this->assertSame('Montag und Dienstag 8–12 Uhr', $this->live->fresh()?->body);
        $this->assertSame(PublicationStatus::Published, $this->live->fresh()?->status);
        $this->get('/aktuelles/oeffnungszeiten')->assertSee('Montag und Dienstag 8–12 Uhr');

        $revision = $this->live->revisions()->firstOrFail();
        $this->assertSame($proposal->applied_revision_number, $revision->revision_number);
        $this->assertStringContainsString('Änderungsvorschlag #'.$proposal->id, (string) $revision->summary);
        $this->assertDatabaseHas('audit_events', ['action' => 'proposal.applied', 'user_id' => $this->publisher->id]);
    }

    public function test_editors_cannot_approve_and_nobody_approves_their_own_proposal(): void
    {
        $proposal = $this->propose($this->editor, ['body' => 'Neu'], 'submit');
        $this->actingAsAdmin($this->editor)->post($this->adminUrl('freigaben/'.$proposal->id.'/freigeben'))->assertForbidden();

        // Four-eyes principle even for publishers proposing changes.
        $own = $this->propose($this->publisher, ['body' => 'Eigener Vorschlag'], 'submit');
        $this->actingAsAdmin($this->publisher)->post($this->adminUrl('freigaben/'.$own->id.'/freigeben'))->assertForbidden();
        $this->assertSame('Montag 8–12 Uhr', $this->live->fresh()?->body);

        config(['admin.proposals.allow_self_approval' => true]);
        $this->actingAsAdmin($this->publisher)->post($this->adminUrl('freigaben/'.$own->id.'/freigeben'))->assertSessionHasNoErrors();
        $this->assertSame('Eigener Vorschlag', $this->live->fresh()?->body);
    }

    public function test_only_the_author_edits_a_proposal(): void
    {
        $proposal = $this->propose($this->editor, ['body' => 'Neu']);
        $other = $this->createUser(Role::Fachbereichsredaktion);

        $this->actingAsAdmin($other)->put($this->adminUrl('freigaben/'.$proposal->id), ['title' => 'Fremd'])->assertForbidden();
        $this->actingAsAdmin($this->publisher)->put($this->adminUrl('freigaben/'.$proposal->id), ['title' => 'Fremd'])->assertForbidden();
    }

    public function test_reject_requires_a_reason_and_keeps_live_content(): void
    {
        $proposal = $this->propose($this->editor, ['body' => 'Falsch'], 'submit');

        $this->actingAsAdmin($this->publisher)->post($this->adminUrl('freigaben/'.$proposal->id.'/ablehnen'), ['review_comment' => ''])
            ->assertSessionHasErrors('review_comment');
        $this->actingAsAdmin($this->publisher)->post($this->adminUrl('freigaben/'.$proposal->id.'/ablehnen'), ['review_comment' => 'Zeiten stimmen nicht'])
            ->assertSessionHasNoErrors();

        $this->assertSame(ProposalStatus::Rejected, $proposal->fresh()?->status);
        $this->assertSame('Montag 8–12 Uhr', $this->live->fresh()?->body);
        $this->actingAsAdmin($this->editor)->get($this->adminUrl('freigaben/'.$proposal->id))->assertSee('Zeiten stimmen nicht');
    }

    public function test_author_can_withdraw(): void
    {
        $proposal = $this->propose($this->editor, ['body' => 'Neu'], 'submit');

        $this->actingAsAdmin($this->editor)->post($this->adminUrl('freigaben/'.$proposal->id.'/zurueckziehen'))->assertSessionHasNoErrors();
        $this->assertSame(ProposalStatus::Withdrawn, $proposal->fresh()?->status);
        $this->actingAsAdmin($this->publisher)->post($this->adminUrl('freigaben/'.$proposal->id.'/freigeben'))->assertForbidden();
    }

    public function test_empty_proposal_cannot_be_submitted(): void
    {
        $this->actingAsAdmin($this->editor)->post($this->adminUrl('artikel/'.$this->live->id.'/vorschlaege'));
        $proposal = ContentProposal::query()->firstOrFail();

        $this->actingAsAdmin($this->editor)->post($this->adminUrl('freigaben/'.$proposal->id.'/einreichen'))->assertSessionHasErrors('general');
        $this->assertSame(ProposalStatus::Draft, $proposal->fresh()?->status);
    }

    public function test_only_changed_parts_are_applied_without_overwriting_newer_live_edits(): void
    {
        $proposal = $this->propose($this->editor, ['body' => 'Neuer Text'], 'submit');

        // Meanwhile the publisher corrects the title directly.
        $this->actingAsAdmin($this->publisher)->put($this->adminUrl('artikel/'.$this->live->id), ['title' => 'Öffnungszeiten Rathaus', 'body' => 'Montag 8–12 Uhr'])
            ->assertSessionHasNoErrors();

        $this->actingAsAdmin($this->publisher)->post($this->adminUrl('freigaben/'.$proposal->id.'/freigeben'))->assertSessionHasNoErrors();

        $this->live->refresh();
        $this->assertSame('Öffnungszeiten Rathaus', $this->live->title, 'Untouched fields keep newer live changes.');
        $this->assertSame('Neuer Text', $this->live->body);
    }

    public function test_conflicting_changes_require_explicit_confirmation(): void
    {
        $proposal = $this->propose($this->editor, ['body' => 'Vorschlag'], 'submit');
        $this->actingAsAdmin($this->publisher)->put($this->adminUrl('artikel/'.$this->live->id), ['title' => $this->live->title, 'body' => 'Direkt geändert']);

        $this->actingAsAdmin($this->publisher)->get($this->adminUrl('freigaben/'.$proposal->id))->assertSee('Konflikt: Direkt geändert');
        $this->actingAsAdmin($this->publisher)->post($this->adminUrl('freigaben/'.$proposal->id.'/freigeben'))->assertSessionHasErrors('confirm_conflicts');
        $this->assertSame('Direkt geändert', $this->live->fresh()?->body);

        $this->actingAsAdmin($this->publisher)->post($this->adminUrl('freigaben/'.$proposal->id.'/freigeben'), ['confirm_conflicts' => '1'])->assertSessionHasNoErrors();
        $this->assertSame('Vorschlag', $this->live->fresh()?->body);
    }

    public function test_relations_and_placements_can_be_proposed(): void
    {
        $tag = Tag::create(['name' => 'Rathaus', 'slug' => 'rathaus']);
        $document = $this->document(['title' => 'Aushang']);

        $proposal = $this->propose($this->editor, ['tags__present' => '1', 'tags' => [$tag->id]]);
        $this->actingAsAdmin($this->editor)->post($this->adminUrl('freigaben/'.$proposal->id.'/zuordnungen'), [
            'kind' => 'documents', 'item_id' => $document->id, 'slot' => 'anhaenge', 'group_label' => '2026', 'sort_order' => 1,
        ])->assertSessionHasNoErrors();
        $this->actingAsAdmin($this->editor)->post($this->adminUrl('freigaben/'.$proposal->id.'/zuordnungen'), [
            'kind' => 'documents', 'item_id' => $document->id, 'slot' => 'ungueltig',
        ])->assertSessionHasErrors('slot');

        $this->assertSame(0, $this->live->tags()->count());
        $this->assertSame(0, $this->live->documents()->count(), 'Live placements unchanged during review.');

        $this->actingAsAdmin($this->editor)->post($this->adminUrl('freigaben/'.$proposal->id.'/einreichen'))->assertSessionHasNoErrors();
        $this->actingAsAdmin($this->publisher)->post($this->adminUrl('freigaben/'.$proposal->id.'/freigeben'))->assertSessionHasNoErrors();

        $this->assertSame([$tag->id], $this->live->tags()->pluck('tags.id')->all());
        $this->assertSame('2026', $this->live->documents()->first()?->pivot->group_label);
    }

    public function test_service_aliases_can_be_proposed(): void
    {
        $service = new Service(['title' => 'Personalausweis']);
        $service->forceFill(['status' => PublicationStatus::Published, 'publish_at' => now()->subDay()])->save();
        $service->aliases()->create(['alias' => 'Perso']);

        $this->actingAsAdmin($this->editor)->post($this->adminUrl('buergerservice/'.$service->id.'/vorschlaege'));
        $proposal = ContentProposal::query()->firstOrFail();
        $this->actingAsAdmin($this->editor)->put($this->adminUrl('freigaben/'.$proposal->id), ['title' => 'Personalausweis', 'aliases' => "Perso\nAusweis", 'action' => 'submit'])
            ->assertSessionHasNoErrors();
        $this->assertSame(['Perso'], $service->aliases()->pluck('alias')->all());

        $this->actingAsAdmin($this->publisher)->post($this->adminUrl('freigaben/'.$proposal->id.'/freigeben'))->assertSessionHasNoErrors();
        $this->assertEqualsCanonicalizing(['Perso', 'Ausweis'], $service->aliases()->pluck('alias')->all());
    }

    public function test_proposals_are_validated_like_direct_edits_and_never_change_publication(): void
    {
        $this->actingAsAdmin($this->editor)->post($this->adminUrl('artikel/'.$this->live->id.'/vorschlaege'));
        $proposal = ContentProposal::query()->firstOrFail();

        $this->actingAsAdmin($this->editor)->put($this->adminUrl('freigaben/'.$proposal->id), ['title' => ''])->assertSessionHasErrors('title');

        $this->actingAsAdmin($this->editor)->put($this->adminUrl('freigaben/'.$proposal->id), [
            'title' => 'X', 'status' => 'archived', 'publish_at' => '2030-01-01T00:00', 'action' => 'submit',
        ])->assertSessionHasNoErrors();
        $this->actingAsAdmin($this->publisher)->post($this->adminUrl('freigaben/'.$proposal->id.'/freigeben'));

        $this->live->refresh();
        $this->assertSame('X', $this->live->title);
        $this->assertSame(PublicationStatus::Published, $this->live->status);
        $this->assertTrue($this->live->publish_at?->isPast());
    }

    public function test_drafts_are_edited_directly_not_proposed(): void
    {
        $draft = $this->article(['title' => 'Entwurf']);

        $this->actingAsAdmin($this->editor)->post($this->adminUrl('artikel/'.$draft->id.'/vorschlaege'))->assertForbidden();
        $this->actingAsAdmin($this->createUser(Role::Reviewer))->post($this->adminUrl('artikel/'.$this->live->id.'/vorschlaege'))->assertForbidden();
    }

    public function test_one_open_proposal_per_author_and_record(): void
    {
        $this->actingAsAdmin($this->editor)->post($this->adminUrl('artikel/'.$this->live->id.'/vorschlaege'));
        $this->actingAsAdmin($this->editor)->post($this->adminUrl('artikel/'.$this->live->id.'/vorschlaege'));

        $this->assertSame(1, ContentProposal::count());
    }

    public function test_proposals_are_backend_only(): void
    {
        $proposal = $this->propose($this->editor, ['body' => 'Geheimer Entwurf'], 'submit');

        Auth::forgetUser();
        $this->get($this->adminUrl('freigaben/'.$proposal->id))->assertRedirect($this->adminUrl('login'));
        $this->get('/aktuelles/oeffnungszeiten')->assertDontSee('Geheimer Entwurf');
        $this->actingAsAdmin($this->createUser(Role::Veranstaltungsredaktion))->get($this->adminUrl('freigaben/'.$proposal->id))->assertForbidden();
    }

    public function test_trashed_record_proposals_cannot_be_applied_and_are_removed_on_purge(): void
    {
        $proposal = $this->propose($this->editor, ['body' => 'Neu'], 'submit');
        $this->live->delete();

        $this->actingAsAdmin($this->publisher)->post($this->adminUrl('freigaben/'.$proposal->id.'/freigeben'))->assertForbidden();

        $this->live->forceDelete();
        $this->assertSame(0, ContentProposal::count());
    }

    public function test_editor_sees_the_propose_action_on_live_content(): void
    {
        $this->actingAsAdmin($this->editor)->get($this->adminUrl('artikel/'.$this->live->id))->assertOk()
            ->assertSee('Änderung vorschlagen')->assertDontSee('>Speichern<', false);
    }
}
