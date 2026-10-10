<?php

namespace Tests\Feature\Content;

use App\Models\Organization;
use App\Models\Page;
use App\Services\Routing\RouteManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_directory_pagination_keeps_every_entry_and_filter_available(): void
    {
        foreach (range(1, 30) as $number) {
            Organization::create(['name' => sprintf('Verein %02d', $number), 'type' => 'verein', 'is_active' => true, 'sort_order' => $number]);
        }
        Organization::create(['name' => 'Privater Verein', 'type' => 'verein', 'is_active' => false]);

        $first = $this->get('/vereine?q=Verein')->assertOk()->assertSee('Verein 24')->assertDontSee('Verein 25')->assertDontSee('Privater Verein');
        $this->assertSame(24, substr_count($first->getContent(), 'class="directory-card"'));
        $first->assertSee('q=Verein&amp;page=2', false);
        $second = $this->get('/vereine?q=Verein&page=2')->assertOk()->assertSee('Verein 25')->assertSee('Verein 30')->assertDontSee('Verein 24');
        $this->assertSame(6, substr_count($second->getContent(), 'class="directory-card"'));
    }

    public function test_source_overview_retains_the_complete_imported_table(): void
    {
        $page = Page::create(['title' => 'Vereine']);
        $page->forceFill(['status' => 'published', 'publish_at' => now()->subDay()])->save();
        app(RouteManager::class)->assign($page, '/vereine');
        $page->blocks()->create(['type' => 'table', 'sort_order' => 0, 'heading' => 'Vereine', 'text' => "Verein\tKontakt\nOriginalverein\tOriginalkontakt"]);

        $this->get('/vereine')->assertOk()->assertSee('directory-source-overview')->assertSee('<caption>Vereine</caption>', false)->assertSee('Originalverein')->assertSee('Originalkontakt');
        $this->assertSame("Verein\tKontakt\nOriginalverein\tOriginalkontakt", $page->blocks()->first()->text);
    }
}
