<?php

namespace Tests\Feature\Content;

use App\Enums\PublicationStatus;
use App\Models\Article;
use App\Models\AuditEvent;
use App\Models\ContentRevision;
use App\Models\Person;
use App\Models\Service;
use App\Models\Tag;
use App\Services\Content\RevisionService;
use App\Support\Authorization\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesContent;
use Tests\TestCase;

class RevisionTest extends TestCase
{
    use CreatesContent, RefreshDatabase;

    public function test_revisions_are_created_on_create_and_update_but_not_for_no_op_saves(): void
    {
        $admin = $this->createUser();
        $this->actingAsAdmin($admin)->post($this->adminUrl('artikel'), ['title' => 'Version 1', 'body' => 'Erster Text']);
        $article = Article::query()->firstOrFail();

        $this->actingAsAdmin($admin)->put($this->adminUrl('artikel/'.$article->id), ['title' => 'Version 2', 'body' => 'Zweiter Text', 'revision_summary' => 'Tippfehler']);
        $this->actingAsAdmin($admin)->put($this->adminUrl('artikel/'.$article->id), ['title' => 'Version 2', 'body' => 'Zweiter Text']);

        $revisions = app(RevisionService::class)->list($article);
        $this->assertSame([2, 1], $revisions->pluck('revision_number')->all());
        $this->assertSame('Tippfehler', $revisions->first()?->summary);
        $this->assertSame($admin->id, $revisions->first()?->user_id);
    }

    public function test_old_revision_can_be_inspected_and_restored_as_new_history(): void
    {
        $admin = $this->createUser();
        $tag = Tag::create(['name' => 'Alt', 'slug' => 'alt']);
        $this->actingAsAdmin($admin)->post($this->adminUrl('artikel'), ['title' => 'Original', 'body' => 'Originaltext', 'tags__present' => '1', 'tags' => [$tag->id]]);
        $article = Article::query()->firstOrFail();
        $this->actingAsAdmin($admin)->put($this->adminUrl('artikel/'.$article->id), ['title' => 'Überarbeitet', 'body' => 'Neu', 'tags__present' => '1']);

        $this->actingAsAdmin($admin)->get($this->adminUrl('artikel/'.$article->id.'/versionen'))->assertOk()->assertSee('Version 2')->assertSee('Version 1');
        $this->actingAsAdmin($admin)->get($this->adminUrl('artikel/'.$article->id.'/versionen/1'))->assertOk()->assertSee('Originaltext');

        $this->actingAsAdmin($admin)->post($this->adminUrl('artikel/'.$article->id.'/versionen/1/wiederherstellen'))->assertRedirect();

        $article->refresh();
        $this->assertSame('Original', $article->title);
        $this->assertSame([$tag->id], $article->tags->pluck('id')->all(), 'Relations are restored too.');
        $this->assertSame([3, 2, 1], $article->revisions()->pluck('revision_number')->all(), 'Restore adds history, never removes it.');
        $this->assertSame('Version 1 wiederhergestellt', $article->revisions()->first()?->summary);
        $this->assertDatabaseHas('audit_events', ['action' => 'revision.restored', 'subject_id' => $article->id]);
    }

    public function test_restore_never_changes_publication_state(): void
    {
        $article = $this->article(['title' => 'Entwurfstext']);
        $service = app(RevisionService::class);
        $first = $service->record($article, null);

        $article->forceFill(['title' => 'Live', 'status' => PublicationStatus::Published, 'publish_at' => now()->subDay()])->save();
        $service->record($article, null);

        $service->restore($first ?? throw new \LogicException, $this->createUser());

        $article->refresh();
        $this->assertSame('Entwurfstext', $article->title);
        $this->assertSame(PublicationStatus::Published, $article->status, 'Going live/offline is always a separate decision.');
    }

    public function test_snapshot_contains_only_allowlisted_editorial_data(): void
    {
        $document = $this->document(['title' => 'Satzung']);
        $snapshot = app(RevisionService::class)->snapshot($document);

        $this->assertSame($document->revisionAttributes(), array_keys($snapshot['attributes']));
        foreach (['id', 'file_path', 'sha256', 'created_by', 'updated_by', 'created_at', 'updated_at', 'deleted_at', 'status', 'publish_at'] as $system) {
            $this->assertArrayNotHasKey($system, $snapshot['attributes'], $system);
        }

        $person = Person::create(['last_name' => 'Beispiel', 'email' => 'p@example.test']);
        $personSnapshot = app(RevisionService::class)->snapshot($person);
        $this->assertArrayNotHasKey('id', $personSnapshot['attributes']);
        $this->assertSame($person->revisionAttributes(), array_keys($personSnapshot['attributes']));
    }

    public function test_revisions_are_immutable(): void
    {
        $revision = app(RevisionService::class)->record($this->article(), null);

        $this->expectException(\LogicException::class);
        $revision?->update(['summary' => 'manipuliert']);
    }

    public function test_restoring_requires_edit_rights(): void
    {
        $article = $this->article();
        app(RevisionService::class)->record($article, null);
        $reviewer = $this->createUser(Role::Reviewer);

        $this->actingAsAdmin($reviewer)->get($this->adminUrl('artikel/'.$article->id.'/versionen'))->assertOk();
        $this->actingAsAdmin($reviewer)->post($this->adminUrl('artikel/'.$article->id.'/versionen/1/wiederherstellen'))->assertForbidden();
        $this->assertSame(1, ContentRevision::count());
    }

    public function test_placement_changes_are_part_of_the_history(): void
    {
        $admin = $this->createUser();
        $page = $this->page();
        $document = $this->document();

        $this->actingAsAdmin($admin)->post($this->adminUrl('seiten/'.$page->id.'/zuordnungen'), [
            'kind' => 'documents', 'item_id' => $document->id, 'slot' => 'downloads', 'group_label' => '2026', 'sort_order' => 3,
        ]);

        $snapshot = $page->revisions()->firstOrFail()->snapshot;
        $this->assertEquals([['id' => $document->id, 'slot' => 'downloads', 'group_label' => '2026', 'sort_order' => 3]], $snapshot['relations']['documents']);
        $this->assertSame(1, $page->revisions()->count(), 'Exactly one revision for one change.');
    }

    public function test_revision_retention_is_configurable_and_keeps_everything_by_default(): void
    {
        $this->assertNull(config('revisions.retention_days'));
        $article = $this->article();
        $service = app(RevisionService::class);

        $this->travelTo(now()->subDays(400));
        foreach (['A', 'B', 'C'] as $title) {
            $article->update(['title' => $title]);
            $service->record($article, null);
        }
        $this->travelBack();

        $this->artisan('revisions:prune')->assertSuccessful();
        $this->assertSame(3, $article->revisions()->count(), 'Nothing is deleted without a policy.');

        config(['revisions.retention_days' => 365, 'revisions.keep_latest' => 1]);
        $this->artisan('revisions:prune')->assertSuccessful();
        $this->assertSame([3], $article->revisions()->pluck('revision_number')->all(), 'Newest revision is always kept.');
        $this->assertSame(0, AuditEvent::query()->where('action', 'revision.pruned')->count(), 'Independent of the audit log.');
    }

    public function test_child_collections_such_as_service_aliases_are_versioned(): void
    {
        $admin = $this->createUser();
        $this->actingAsAdmin($admin)->post($this->adminUrl('buergerservice'), ['title' => 'Personalausweis', 'aliases' => "Perso\nAusweis"]);
        $service = Service::query()->firstOrFail();
        $this->actingAsAdmin($admin)->put($this->adminUrl('buergerservice/'.$service->id), ['title' => 'Personalausweis', 'aliases' => 'Reisepass']);

        $this->assertSame(['Reisepass'], $service->aliases()->pluck('alias')->all());
        $this->assertEquals([['alias' => 'Ausweis'], ['alias' => 'Perso']], $service->revisions()->where('revision_number', 1)->firstOrFail()->snapshot['collections']['aliases']);

        $this->actingAsAdmin($admin)->post($this->adminUrl('buergerservice/'.$service->id.'/versionen/1/wiederherstellen'))->assertRedirect();
        $this->assertEqualsCanonicalizing(['Perso', 'Ausweis'], $service->aliases()->pluck('alias')->all());
    }
}
