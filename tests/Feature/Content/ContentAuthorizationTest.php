<?php

namespace Tests\Feature\Content;

use App\Enums\PublicationStatus;
use App\Models\Article;
use App\Support\Authorization\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesContent;
use Tests\TestCase;

class ContentAuthorizationTest extends TestCase
{
    use CreatesContent, RefreshDatabase;

    public function test_user_without_permissions_cannot_do_anything(): void
    {
        $nobody = $this->userWithPermissions([]);
        $article = $this->article();

        $this->actingAsAdmin($nobody)->get($this->adminUrl('artikel'))->assertForbidden();
        $this->actingAsAdmin($nobody)->get($this->adminUrl('artikel/neu'))->assertForbidden();
        $this->actingAsAdmin($nobody)->post($this->adminUrl('artikel'), ['title' => 'X'])->assertForbidden();
        $this->actingAsAdmin($nobody)->get($this->adminUrl('artikel/'.$article->id))->assertForbidden();
        $this->actingAsAdmin($nobody)->put($this->adminUrl('artikel/'.$article->id), ['title' => 'Y'])->assertForbidden();
        $this->actingAsAdmin($nobody)->delete($this->adminUrl('artikel/'.$article->id))->assertForbidden();

        $this->assertSame(1, Article::count());
        $this->assertNotSame('Y', $article->fresh()?->title);
    }

    public function test_reviewer_can_view_but_not_change(): void
    {
        $reviewer = $this->createUser(Role::Reviewer);
        $article = $this->article(['title' => 'Nur lesen']);

        $this->actingAsAdmin($reviewer)->get($this->adminUrl('artikel'))->assertOk();
        $this->actingAsAdmin($reviewer)->get($this->adminUrl('artikel/'.$article->id))->assertOk()
            ->assertSee('nicht ändern')->assertDontSee('>Speichern<', false);
        $this->actingAsAdmin($reviewer)->put($this->adminUrl('artikel/'.$article->id), ['title' => 'Geändert'])->assertForbidden();
    }

    public function test_edit_permission_does_not_imply_publish_permission(): void
    {
        $editor = $this->createUser(Role::Fachbereichsredaktion);

        // A draft can be created …
        $this->actingAsAdmin($editor)->post($this->adminUrl('artikel'), ['title' => 'Entwurf'])->assertSessionHasNoErrors();
        $draft = Article::query()->where('title', 'Entwurf')->firstOrFail();
        $this->assertSame(PublicationStatus::Draft, $draft->status);

        // … but not published, neither on create nor on update.
        $this->actingAsAdmin($editor)->post($this->adminUrl('artikel'), ['title' => 'Direkt live', 'status' => 'published'])->assertForbidden();
        $this->actingAsAdmin($editor)->put($this->adminUrl('artikel/'.$draft->id), ['title' => 'Entwurf', 'status' => 'published'])->assertForbidden();
        $this->assertSame(PublicationStatus::Draft, $draft->fresh()?->status);
        $this->assertSame(0, Article::visible()->count());

        // The form does not even offer the status field.
        $this->actingAsAdmin($editor)->get($this->adminUrl('artikel/'.$draft->id))->assertOk()
            ->assertDontSee('name="status"', false)->assertSee('Veröffentlichungsrecht');
    }

    public function test_editor_cannot_change_live_content_or_its_publication_window(): void
    {
        $editor = $this->createUser(Role::Fachbereichsredaktion);
        $live = $this->article(['title' => 'Live'], PublicationStatus::Published, now()->subDay());

        $this->actingAsAdmin($editor)->put($this->adminUrl('artikel/'.$live->id), ['title' => 'Heimlich geändert'])->assertForbidden();
        $this->actingAsAdmin($editor)->delete($this->adminUrl('artikel/'.$live->id))->assertForbidden();
        $this->assertSame('Live', $live->fresh()?->title);
    }

    public function test_publisher_can_publish_and_change_window(): void
    {
        $chief = $this->createUser(Role::Chefredaktion);
        $draft = $this->article(['title' => 'Bald online']);

        $this->actingAsAdmin($chief)->put($this->adminUrl('artikel/'.$draft->id), [
            'title' => 'Bald online', 'status' => 'published', 'publish_at' => '2030-01-01T09:00', 'expires_at' => '2030-02-01T09:00',
        ])->assertSessionHasNoErrors();

        $draft->refresh();
        $this->assertSame(PublicationStatus::Published, $draft->status);
        $this->assertSame('2030-01-01 08:00:00', $draft->publish_at?->format('Y-m-d H:i:s'), 'Site time converted to UTC.');
        $this->assertDatabaseHas('audit_events', ['action' => 'content.published', 'subject_id' => $draft->id]);
    }

    public function test_end_before_start_is_rejected_in_the_form(): void
    {
        $chief = $this->createUser(Role::Chefredaktion);

        $this->actingAsAdmin($chief)->post($this->adminUrl('artikel'), [
            'title' => 'X', 'status' => 'published', 'publish_at' => '2030-01-02T09:00', 'expires_at' => '2030-01-01T09:00',
        ])->assertSessionHasErrors('expires_at');
    }

    public function test_archiving_requires_archive_permission(): void
    {
        $publisher = $this->userWithPermissions(['article.view', 'article.edit', 'article.publish']);
        $live = $this->article(['title' => 'Live'], PublicationStatus::Published, now()->subDay());

        $this->actingAsAdmin($publisher)->put($this->adminUrl('artikel/'.$live->id), ['title' => 'Live', 'status' => 'archived'])->assertForbidden();

        $archivist = $this->userWithPermissions(['article.view', 'article.edit', 'article.publish', 'article.archive']);
        $this->actingAsAdmin($archivist)->put($this->adminUrl('artikel/'.$live->id), ['title' => 'Live', 'status' => 'archived'])->assertSessionHasNoErrors();
        $this->assertSame(PublicationStatus::Archived, $live->fresh()?->status);
        $this->assertNotNull($live->fresh()?->archived_at);
    }

    public function test_invalid_status_transition_is_rejected(): void
    {
        $admin = $this->createUser();
        $draft = $this->article();

        $this->actingAsAdmin($admin)->put($this->adminUrl('artikel/'.$draft->id), ['title' => 'X', 'status' => 'archived'])
            ->assertSessionHasErrors('status');
    }

    public function test_soft_delete_restore_and_permanent_delete(): void
    {
        $chief = $this->createUser(Role::Chefredaktion);
        $admin = $this->createUser(Role::Administrator);
        $article = $this->article(['title' => 'Papierkorb-Test']);

        $this->actingAsAdmin($chief)->delete($this->adminUrl('artikel/'.$article->id))->assertRedirect();
        $this->assertSoftDeleted($article);

        // Disappears from normal lists, appears in the recycle bin.
        $this->actingAsAdmin($chief)->get($this->adminUrl('artikel'))->assertDontSee('>Papierkorb-Test</a>', false);
        $this->actingAsAdmin($chief)->get($this->adminUrl('artikel?papierkorb=1'))->assertSee('>Papierkorb-Test</a>', false);

        // Chefredaktion may restore, but never permanently delete.
        $this->actingAsAdmin($chief)->delete($this->adminUrl('artikel/'.$article->id.'/endgueltig'))->assertForbidden();
        $this->actingAsAdmin($chief)->post($this->adminUrl('artikel/'.$article->id.'/wiederherstellen'))->assertRedirect();
        $this->assertNotSoftDeleted($article);

        // Permanent deletion only from the recycle bin and only with force-delete.
        $this->actingAsAdmin($admin)->delete($this->adminUrl('artikel/'.$article->id.'/endgueltig'))->assertForbidden();
        $this->actingAsAdmin($admin)->delete($this->adminUrl('artikel/'.$article->id));
        $this->actingAsAdmin($admin)->delete($this->adminUrl('artikel/'.$article->id.'/endgueltig'))->assertRedirect();
        $this->assertModelMissing($article);

        foreach (['content.deleted', 'content.restored', 'content.force_deleted'] as $action) {
            $this->assertDatabaseHas('audit_events', ['action' => $action]);
        }
    }

    public function test_trashed_content_cannot_be_edited_and_is_not_public(): void
    {
        $admin = $this->createUser();
        $page = $this->page(['title' => 'Gelöschte Seite']);
        $page->delete();

        $this->assertFalse($page->isPubliclyReachable());
        $this->actingAsAdmin($admin)->put($this->adminUrl('seiten/'.$page->id), ['title' => 'X'])->assertForbidden();
    }

    public function test_dashboard_lists_only_permitted_areas(): void
    {
        $eventsOnly = $this->userWithPermissions(['event.view']);

        $this->actingAsAdmin($eventsOnly)->get($this->adminUrl('dashboard'))->assertOk()
            ->assertSee('Veranstaltungen')->assertDontSee('Weiterleitungen')->assertDontSee('Benutzerkonten');
    }
}
