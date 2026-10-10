<?php

namespace Tests\Feature\Content;

use App\Exceptions\DomainRuleViolation;
use App\Models\Page;
use App\Services\Content\RowDefinitions;
use App\Services\Routing\RouteManager;
use App\Support\Content\ControlledTable;
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

    public function test_directory_panels_preserve_every_original_cell_and_safe_link_in_order(): void
    {
        $text = "Merkmal\tAngabe\n**Name:**\tGasthof Eins\n**E-Mail:**\t[Kontakt](mailto:info@example.org)\n**Beschreibung:**\t\n\tzurück\n**Name:**\tGasthof Zwei\n**Telefon:**\t08233 123";
        $rows = ControlledTable::rows($text);
        $groups = ControlledTable::recordGroups($rows);
        $this->assertNotNull($groups);
        $this->assertCount(2, $groups);
        $this->assertSame(array_slice($rows, 1), array_merge(...$groups));

        $page = Page::create(['title' => 'Gaststätten']);
        $page->forceFill(['status' => 'published', 'publish_at' => now()->subDay()])->save();
        app(RouteManager::class)->assign($page, '/gaststaetten');
        $page->blocks()->create(['type' => 'table', 'sort_order' => 0, 'heading' => 'Informationen', 'text' => $text]);
        $this->get('/gaststaetten')->assertOk()->assertSee('class="record-facts"', false)->assertSeeInOrder(['Gasthof Eins', 'mailto:info@example.org', 'Beschreibung:', 'zurück', 'Gasthof Zwei', '08233 123'])->assertDontSee('<table', false);
    }

    public function test_comparison_and_single_record_tables_keep_their_table_semantics(): void
    {
        $this->assertNull(ControlledTable::recordGroups(ControlledTable::rows("Merkmal\tAngabe\tWeitere Angaben\nName\tEins\tZwei")));
        $this->assertNull(ControlledTable::recordGroups(ControlledTable::rows("Merkmal\tAngabe\n**Name:**\tGasthof Eins\nTelefon\t123")));
        $this->assertNull(ControlledTable::recordGroups(ControlledTable::rows("Merkmal\tAngabe\nBreitband\tGigabit\nTrinkwasser\tHart")));
    }
}
