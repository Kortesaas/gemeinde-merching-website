<?php

namespace Tests\Feature\Content;

use App\Exceptions\DomainRuleViolation;
use App\Models\Page;
use App\Services\Content\RowDefinitions;
use App\Services\Routing\RouteManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ControlledTableTest extends TestCase
{
    use RefreshDatabase;

    public function test_table_has_caption_column_headers_safe_links_and_no_executable_markup(): void
    {
        $page = Page::create(['title' => 'Standortfaktoren']);
        $page->forceFill(['status' => 'published', 'publish_at' => now()->subDay()])->save();
        app(RouteManager::class)->assign($page, '/standortfaktoren');
        $page->blocks()->create(['type' => 'table', 'sort_order' => 0, 'heading' => 'Öffentliche Angaben', 'text' => "Merkmal\tAngabe\nBreitband\t[Information](/buergerservice)"]);
        $this->get('/standortfaktoren')->assertOk()->assertSee('<caption>Öffentliche Angaben</caption>', false)->assertSee('scope="col"', false)->assertSee('href="/buergerservice"', false);
        $this->expectException(DomainRuleViolation::class);
        app(RowDefinitions::class)->validate('blocks', ['type' => 'table', 'sort_order' => 0, 'heading' => 'Unsafe', 'text' => "A\tB\n<script>alert(1)</script>\tC"]);
    }

    public function test_ragged_tables_are_rejected(): void
    {
        $this->expectException(DomainRuleViolation::class);
        app(RowDefinitions::class)->validate('blocks', ['type' => 'table', 'sort_order' => 0, 'heading' => 'Invalid', 'text' => "A\tB\nC"]);
    }
}
