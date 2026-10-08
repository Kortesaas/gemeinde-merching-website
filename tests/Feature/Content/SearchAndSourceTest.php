<?php

namespace Tests\Feature\Content;

use App\Models\Category;
use App\Models\Person;
use App\Models\Service;
use App\Models\SourceReference;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesContent;
use Tests\TestCase;

class SearchAndSourceTest extends TestCase
{
    use CreatesContent, RefreshDatabase;

    public function test_models_expose_searchable_text_for_mysql_search(): void
    {
        $category = Category::create(['context' => 'service', 'name' => 'Meldewesen', 'slug' => 'meldewesen']);
        $service = Service::create(['title' => 'Personalausweis', 'summary' => 'Antrag', 'category_id' => $category->id]);
        $service->aliases()->create(['alias' => 'Perso']);

        $document = $service->fresh()?->toSearchDocument();
        $this->assertNotNull($document);
        $this->assertSame('Personalausweis', $document->title);
        $this->assertContains('Perso', $document->keywords);
        $this->assertContains('Meldewesen', $document->keywords);

        $person = Person::create(['last_name' => 'Beispiel', 'job_title' => 'Sachbearbeitung', 'responsibilities' => 'Gewerbeanmeldung']);
        $this->assertStringContainsString('Gewerbeanmeldung', $person->toSearchDocument()->text());
    }

    public function test_source_references_record_migration_provenance(): void
    {
        $page = $this->page();
        $page->sourceReferences()->create(['source_system' => 'wordpress', 'source_id' => '4711', 'original_url' => 'https://www.gemeinde-merching.de/?p=4711', 'imported_at' => now()]);

        $this->assertSame('page', SourceReference::query()->firstOrFail()->referenceable_type);

        try {
            $this->page()->sourceReferences()->create(['source_system' => 'wordpress', 'source_id' => '4711']);
            $this->fail('Duplicate source id accepted');
        } catch (QueryException) {
            $this->assertSame(1, SourceReference::count());
        }

        $page->forceDelete();
        $this->assertSame(0, SourceReference::count());
    }
}
