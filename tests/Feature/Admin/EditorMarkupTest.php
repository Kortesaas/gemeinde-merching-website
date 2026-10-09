<?php

namespace Tests\Feature\Admin;

use App\Models\NavigationItem;
use App\Services\Routing\RouteManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesContent;
use Tests\TestCase;

/** The editor form must stay well-formed: uploads depend on its enctype. */
class EditorMarkupTest extends TestCase
{
    use CreatesContent, RefreshDatabase;

    public function test_upload_editors_send_multipart_forms_and_have_no_stray_markup(): void
    {
        $editor = $this->userWithPermissions(['document.view', 'document.create', 'media.view', 'media.create']);
        foreach (['dokumente/neu', 'medien/neu'] as $path) {
            $html = (string) $this->actingAsAdmin($editor)->get($this->adminUrl($path))->assertOk()->getContent();
            $this->assertMatchesRegularExpression('/<form id="editor-form"[^>]*enctype="multipart\/form-data"[^>]*novalidate>/', $html);
            $this->assertStringNotContainsString('novalidate&gt;', $html);
            $this->assertSame(1, substr_count($html, '<form id="editor-form"'));
        }
    }

    public function test_page_editor_groups_fields_and_keeps_save_actions_inside_the_form(): void
    {
        $page = $this->page(['title' => 'Gruppierte Testseite']);
        $editor = $this->userWithPermissions(['page.view', 'page.edit', 'page.publish']);
        $this->actingAsAdmin($editor)->get($this->adminUrl('seiten/'.$page->id))->assertOk()
            ->assertSeeInOrder(['id="bereich-inhalt"', 'id="bereich-inhaltsbausteine"', 'id="publication"', 'id="quality"', 'id="public-route"'], false)
            ->assertSee('form="editor-form"', false);
    }

    public function test_navigation_editors_load_content_labels_with_lazy_loading_prevented(): void
    {
        $admin = $this->createUser();
        $first = $this->page(['title' => 'Erstes Navigationsziel']);
        $second = $this->page(['title' => 'Zweites Navigationsziel']);
        app(RouteManager::class)->assign($first, '/erstes-ziel');
        app(RouteManager::class)->assign($second, '/zweites-ziel');
        $item = NavigationItem::create(['menu' => 'main', 'label' => 'Menüpunkt', 'url' => 'https://example.test', 'is_active' => true]);
        $before = Model::preventsLazyLoading();
        Model::preventLazyLoading();
        try {
            foreach (['navigation/neu', 'navigation/'.$item->id] as $path) {
                $this->actingAsAdmin($admin)->get($this->adminUrl($path))->assertOk()
                    ->assertSee('Erstes Navigationsziel')->assertSee('Zweites Navigationsziel');
            }
        } finally {
            Model::preventLazyLoading($before);
        }
    }
}
