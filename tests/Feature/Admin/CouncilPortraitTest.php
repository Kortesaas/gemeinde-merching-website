<?php

namespace Tests\Feature\Admin;

use App\Admin\Resources\CouncilMemberResource;
use App\Models\ContentRevision;
use App\Models\CouncilMember;
use App\Models\CouncilTerm;
use App\Models\Media;
use App\Services\Content\ContentUsage;
use App\Services\Content\MediaStorage;
use App\Services\Content\ProposalService;
use App\Services\Content\RevisionService;
use App\Services\Routing\RouteManager;
use App\Support\Authorization\Role;
use Database\Seeders\Demo\DemoFiles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CouncilPortraitTest extends TestCase
{
    use RefreshDatabase;

    private function portrait(): Media
    {
        Storage::fake(config('uploads.disk'));
        $media = new Media(['title' => 'Demo-Porträt', 'alt_text' => 'Demo-Person', 'focal_x' => 37, 'focal_y' => 28]);
        app(MediaStorage::class)->attach($media, UploadedFile::fake()->createWithContent('portrait.png', DemoFiles::portrait(1)));
        $media->forceFill(['status' => 'published', 'publish_at' => now()->subDay()])->save();

        return $media;
    }

    public function test_council_portraits_and_local_placeholders_have_empty_alt_next_to_names(): void
    {
        $media = $this->portrait();
        $term = CouncilTerm::create(['title' => 'Gemeinderat']);
        $term->forceFill(['status' => 'published', 'publish_at' => now()->subDay()])->save();
        app(RouteManager::class)->assign($term, '/gemeinderat');
        foreach (['Mit Bild' => $media->id, 'Ohne Bild' => null] as $name => $id) {
            $member = CouncilMember::create(['title' => $name, 'portrait_id' => $id]);
            $member->forceFill(['status' => 'published', 'publish_at' => now()->subDay()])->save();
            $term->memberships()->create(['council_member_id' => $member->id, 'role' => 'Mitglied', 'sort_order' => $id ? 0 : 1]);
        }
        $this->get('/gemeinderat')->assertOk()->assertSee('Mit Bild')->assertSee('Ohne Bild')->assertSee('council-placeholder.svg')->assertSee('focal-x-37 focal-y-28', false)->assertSee('alt=""', false)->assertDontSee('alt="Demo-Person"', false);
        $this->get('/medien/'.$media->id)->assertOk();
    }

    public function test_portrait_selection_supports_proposals_and_revisions_and_blocks_media_deletion(): void
    {
        $media = $this->portrait();
        $publisher = $this->createUser();
        $editor = $this->createUser(Role::Fachbereichsredaktion);
        $member = CouncilMember::create(['title' => 'Council member']);
        $resource = app(CouncilMemberResource::class);
        $resource->save($member, ['status' => 'published'], Request::create('/'), $publisher);
        $original = $member->revisions()->first();
        $service = app(ProposalService::class);
        $proposal = $service->create($member, $editor);
        $service->update($proposal, $resource, ['portrait_id' => $media->id], Request::create('/'), $editor);
        $this->assertNull($member->refresh()->portrait_id);
        $this->assertTrue(app(ContentUsage::class)->isUsed($media));
        $service->submit($proposal, $editor);
        $service->apply($proposal, $publisher, false);
        $this->assertSame($media->id, $member->refresh()->portrait_id);
        app(RevisionService::class)->restore($original, $publisher);
        $this->assertNull($member->refresh()->portrait_id);
        $this->assertTrue(app(ContentUsage::class)->isUsed($media));
        $media->delete();
        $this->actingAsAdmin($publisher)->delete(route('admin.media.force-delete', $media->id))->assertSessionHasErrors();
        $this->assertNotNull(Media::withTrashed()->find($media->id));
    }

    public function test_employee_forms_do_not_gain_portrait_support(): void
    {
        $publisher = $this->createUser();
        $this->actingAsAdmin($publisher)->get(route('admin.person.create'))->assertOk()->assertDontSee('portrait_id');
        $this->get(route('admin.ratsmitglieder.create'))->assertOk()->assertSee('Porträt (optional)');
    }

    public function test_restoring_a_revision_from_before_portrait_support_removes_the_portrait(): void
    {
        $media = $this->portrait();
        $publisher = $this->createUser();
        $member = CouncilMember::create(['title' => 'Legacy member', 'portrait_id' => $media->id]);
        $snapshot = app(RevisionService::class)->snapshot($member);
        unset($snapshot['attributes']['portrait_id']);
        $revision = ContentRevision::create(['revisionable_type' => $member->getMorphClass(), 'revisionable_id' => $member->id, 'revision_number' => 1, 'user_id' => $publisher->id, 'snapshot' => $snapshot]);
        app(RevisionService::class)->restore($revision, $publisher);
        $this->assertNull($member->refresh()->portrait_id);
    }
}
