<?php

namespace Tests\Feature\Content;

use App\Models\Article;
use App\Models\AuditEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_cms_actions_are_audited_without_content_bodies(): void
    {
        $admin = $this->createUser();
        $body = 'Sehr langer vertraulicher Entwurfstext, der nicht ins Protokoll gehört.';

        $this->actingAsAdmin($admin)->post($this->adminUrl('artikel'), ['title' => 'Audit', 'body' => $body]);
        $article = Article::query()->firstOrFail();
        $this->actingAsAdmin($admin)->put($this->adminUrl('artikel/'.$article->id), ['title' => 'Audit 2', 'body' => $body, 'status' => 'published']);
        $this->actingAsAdmin($admin)->put($this->adminUrl('artikel/'.$article->id), ['title' => 'Audit 2', 'body' => $body, 'status' => 'archived']);
        $this->actingAsAdmin($admin)->delete($this->adminUrl('artikel/'.$article->id));

        $actions = AuditEvent::query()->where('subject_type', 'article')->pluck('action')->all();
        foreach (['content.created', 'content.updated', 'content.published', 'content.archived', 'content.deleted', 'route.changed'] as $expected) {
            $this->assertContains($expected, $actions);
        }

        $updated = AuditEvent::query()->where('action', 'content.updated')->firstOrFail();
        $this->assertContains('title', $updated->metadata['changed'] ?? []);

        foreach (AuditEvent::all() as $event) {
            $this->assertStringNotContainsString('vertraulicher', (string) json_encode($event->metadata));
        }
    }
}
