<?php

namespace Tests\Feature\Content;

use App\Admin\ResourceRegistry;
use App\Enums\PublicationStatus;
use App\Models\Category;
use App\Models\Department;
use App\Models\Person;
use App\Models\SearchSynonym;
use App\Models\Service;
use App\Services\Content\ProposalService;
use App\Services\Routing\RouteManager;
use App\Services\Search\SearchStatistics;
use App\Services\Search\SiteSearch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesContent;
use Tests\TestCase;

class SearchFoundationTest extends TestCase
{
    use CreatesContent,RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake((string) config('uploads.disk'));
    }

    private function service(string $title = 'Personalausweis'): Service
    {
        $s = Service::create(['title' => $title, 'summary' => 'Beantragung beim Bürgerbüro']);
        $s->forceFill(['status' => 'published', 'publish_at' => now()->subDay()])->save();
        app(RouteManager::class)->assign($s, '/leistung/'.$s->id);

        return $s;
    }

    public function test_index_is_maintained_on_save_soft_delete_and_restore(): void
    {
        $p = $this->page(['title' => 'Abfallentsorgung']);
        app(RouteManager::class)->assign($p, '/abfall');
        $this->assertDatabaseHas('search_entries', ['content_type' => 'page', 'title' => 'Abfallentsorgung']);
        $p->update(['title' => 'Wertstoffentsorgung']);
        $this->assertSame(1, app(SiteSearch::class)->search('Wertstoff')['total']);
        $p->delete();
        $this->assertDatabaseCount('search_entries', 0);
        $p->restore();
        $this->assertSame(1, app(SiteSearch::class)->search('Wertstoff')['total']);
    }

    public function test_short_terms_and_wildcards_are_safe(): void
    {
        $s = $this->service('ÖPNV');
        $search = app(SiteSearch::class);
        $this->assertSame(1, $search->search('ÖP')['total']);
        $this->assertSame(0, $search->search('%')['total']);
        $this->assertSame(0, $search->search("'; DROP TABLE pages; --")['total']);
    }

    public function test_synonyms_expand_both_directions_and_inactive_entries_do_not(): void
    {
        $s = $this->service();
        SearchSynonym::create(['phrase' => 'Bürgerkarte', 'alternatives' => 'Personalausweis; Ausweis', 'is_active' => true]);
        $this->assertSame(1, app(SiteSearch::class)->search('Bürgerkarte')['total']);
        SearchSynonym::query()->update(['is_active' => false]);
        $this->assertSame(0, app(SiteSearch::class)->search('Bürgerkarte')['total']);
    }

    public function test_service_aliases_are_searchable_and_removal_updates_index(): void
    {
        $s = $this->service();
        $alias = $s->aliases()->create(['alias' => 'Identitätskarte']);
        $this->assertSame(1, app(SiteSearch::class)->search('Identitätskarte')['total']);
        $alias->delete();
        $this->assertSame(0, app(SiteSearch::class)->search('Identitätskarte')['total']);
    }

    public function test_type_filtering_and_title_ranking(): void
    {
        $s = $this->service('Hundesteuer');
        $p = $this->page(['title' => 'Weitere Informationen', 'body' => 'Hundesteuer']);
        app(RouteManager::class)->assign($p, '/infos');
        $results = app(SiteSearch::class)->search('Hundesteuer');
        $this->assertSame('service', $results['results'][0]['type']);
        $this->assertSame(1, app(SiteSearch::class)->search('Hundesteuer', ['page'])['total']);
    }

    public function test_unsupported_type_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        app(SiteSearch::class)->search('x', ['user']);
    }

    public function test_visibility_uses_live_publication_window_without_reindexing(): void
    {
        $p = $this->page(['title' => 'Zeitfenster']);
        app(RouteManager::class)->assign($p, '/zeitfenster');
        $p->forceFill(['publish_at' => now()->addHour(), 'expires_at' => now()->addHours(2)])->save();
        $this->assertSame(0, app(SiteSearch::class)->search('Zeitfenster')['total']);
        $this->travel(61)->minutes();
        $this->assertSame(1, app(SiteSearch::class)->search('Zeitfenster')['total']);
        $this->travel(60)->minutes();
        $this->assertSame(0, app(SiteSearch::class)->search('Zeitfenster')['total']);
    }

    public function test_drafts_and_unrouted_directories_are_excluded_but_archived_documents_remain(): void
    {
        $this->page(['title' => 'Gesucht'], PublicationStatus::Draft);
        Department::create(['name' => 'Gesucht', 'is_active' => true]);
        $d = $this->document(['title' => 'Gesucht']);
        $d->forceFill(['expires_at' => now()->subHour()])->save();
        $result = app(SiteSearch::class)->search('Gesucht');
        $this->assertSame(1, $result['total']);
        $this->assertSame('document', $result['results'][0]['type']);
        $this->get($d->downloadPath())->assertOk();
    }

    public function test_people_results_link_to_public_context_without_profile_url(): void
    {
        $person = Person::create(['last_name' => 'Musterperson', 'responsibilities' => 'Hundesteuer', 'is_active' => true]);
        $this->assertSame(0, app(SiteSearch::class)->search('Musterperson')['total']);
        $s = $this->service();
        $s->contacts()->attach($person->id);
        $result = app(SiteSearch::class)->search('Musterperson', ['person']);
        $this->assertSame(1, $result['total']);
        $this->assertStringEndsWith($s->publicPath(), $result['results'][0]['url']);
        $person->update(['is_active' => false]);
        $this->assertSame(0, app(SiteSearch::class)->search('Musterperson')['total']);
    }

    public function test_document_metadata_and_category_changes_are_indexed(): void
    {
        $category = Category::create(['name' => 'Ortsrecht', 'context' => 'document', 'slug' => 'ortsrecht']);
        $this->document(['title' => 'Satzung', 'category_id' => $category->id, 'year' => 2026]);
        $this->assertSame(1, app(SiteSearch::class)->search('Ortsrecht', ['document'])['total']);
        $category->update(['name' => 'Gemeinderecht']);
        $this->assertSame(1, app(SiteSearch::class)->search('Gemeinderecht')['total']);
    }

    public function test_rebuild_command_recovers_missing_index(): void
    {
        $this->service();
        DB::table('search_entries')->delete();
        $this->artisan('search:rebuild')->assertSuccessful();
        $this->assertDatabaseCount('search_entries', 1);
    }

    public function test_statistics_are_opt_in_and_have_no_network_or_user_columns(): void
    {
        $s = app(SearchStatistics::class);
        $s->record('Reisepass', 0);
        $this->assertDatabaseCount('search_statistics', 0);
        config(['search.statistics_enabled' => true]);
        $s->record('Reisepass', 3);
        $this->assertDatabaseHas('search_statistics', ['phrase' => 'Reisepass', 'result_count' => 3, 'result_clicked' => null]);
        $this->assertSame(['id', 'phrase', 'result_count', 'result_clicked', 'created_at'], Schema::getColumnListing('search_statistics'));
    }

    public function test_obvious_personal_identifiers_are_not_retained(): void
    {
        config(['search.statistics_enabled' => true]);
        foreach (['a@example.test', '08233 123456', 'https://example.test', '1234567890'] as $phrase) {
            app(SearchStatistics::class)->record($phrase, 0);
        }
        $this->assertDatabaseCount('search_statistics', 0);
    }

    public function test_statistics_retention_prunes_at_record_time_and_by_command(): void
    {
        config(['search.statistics_enabled' => true, 'search.retention_days' => 2]);
        app(SearchStatistics::class)->record('Reisepass', 2);
        $this->travel(3)->days();
        app(SearchStatistics::class)->record('Hundesteuer', 1);
        $this->assertDatabaseCount('search_statistics', 1);
        config(['search.retention_days' => 0]);
        $this->travel(1)->seconds();
        $this->artisan('search:prune-statistics')->assertSuccessful();
        $this->assertDatabaseCount('search_statistics', 0);
    }

    public function test_synonym_admin_permissions_validation_and_revisions(): void
    {
        $this->actingAsAdmin($this->createUser())->post($this->adminUrl('suchbegriffe'), ['phrase' => 'Bürgerkarte', 'alternatives' => 'Personalausweis', 'is_active' => 1])->assertSessionHasNoErrors();
        $s = SearchSynonym::query()->firstOrFail();
        $this->assertSame(1, $s->revisions()->count());
        $this->actingAsAdmin($this->userWithPermissions(['search-synonym.view']))->put($this->adminUrl('suchbegriffe/'.$s->id), ['phrase' => 'X', 'alternatives' => 'Y'])->assertForbidden();
    }

    public function test_proposal_index_changes_remain_private_until_approval(): void
    {
        $p = $this->page(['title' => 'Öffentlicher Stand']);
        app(RouteManager::class)->assign($p, '/such-proposal');
        $author = $this->userWithPermissions(['page.edit', 'page.view']);
        $publisher = $this->createUser();
        $service = app(ProposalService::class);
        $proposal = $service->create($p, $author);
        $service->update($proposal, ResourceRegistry::get('page'), ['title' => 'Privater Vorschlag'], Request::create('/', 'POST'), $author);
        $this->assertSame(0, app(SiteSearch::class)->search('Privater Vorschlag')['total']);
        $service->submit($proposal->refresh(), $author);
        $service->apply($proposal->refresh(), $publisher, false);
        $this->assertSame(1, app(SiteSearch::class)->search('Privater Vorschlag')['total']);
    }

    public function test_empty_query_paging_and_current_route_use(): void
    {
        $s = $this->service('Tiersteuer');
        $p = $this->page(['title' => 'Tiersteuer Info']);
        app(RouteManager::class)->assign($p, '/tier');
        $search = app(SiteSearch::class);
        $this->assertSame(0, $search->search('   ')['total']);
        $this->assertCount(1, $search->search('Tiersteuer', [], 1)['results']);
        app(RouteManager::class)->assign($s, '/neue-leistung');
        $this->assertStringEndsWith('/neue-leistung', $search->search('Tiersteuer', [], 1)['results'][0]['url']);
    }
}
