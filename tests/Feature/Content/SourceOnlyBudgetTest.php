<?php

namespace Tests\Feature\Content;

use App\Models\BudgetPlan;
use App\Models\BudgetPublication;
use App\Models\BudgetSource;
use App\Services\Routing\RouteManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SourceOnlyBudgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_snapshot_sources_are_downloadable_when_merging_failed(): void
    {
        Storage::fake('local');
        $bytes = "%PDF-1.4\npublic original";
        Storage::disk('local')->put('budget/test.pdf', $bytes);
        $plan = BudgetPlan::create(['year' => 2026, 'topic' => 'Gemeinde', 'title' => 'Haushaltsplan']);
        $plan->forceFill(['status' => 'published', 'publish_at' => now()->subDay()])->save();
        app(RouteManager::class)->assign($plan, '/haushaltsplaene/2026/gemeinde');
        $source = new BudgetSource;
        $source->forceFill(['budget_plan_id' => $plan->id, 'file_path' => 'budget/test.pdf', 'original_filename' => 'Original.pdf', 'size_bytes' => strlen($bytes), 'sha256' => hash('sha256', $bytes)])->save();
        $receipt = new BudgetPublication;
        $receipt->forceFill(['budget_plan_id' => $plan->id, 'year' => 2026, 'title' => $plan->title, 'status' => 'published', 'publish_at' => $plan->publish_at, 'publisher_name' => 'Local import', 'public_url' => $plan->publicPath(), 'accessibility_status' => 'not_checked', 'show_components' => true, 'source_only' => true, 'source_manifest' => [['id' => $source->id, 'sha256' => $source->getAttribute('sha256'), 'size_bytes' => strlen($bytes), 'original_filename' => 'Original.pdf']]])->save();
        $base = '/haushaltsplaene/2026/paket/'.$plan->id;
        $this->get($base.'/gesamt.pdf')->assertNotFound();
        $this->get($base.'/quelle/'.$source->id.'.pdf')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get($base.'/quelle/999.pdf')->assertNotFound();
        $this->get($plan->publicPath())->assertOk()->assertSee('Originaldateien')->assertDontSee('Gesamt-PDF herunterladen');
        Storage::disk('local')->put('budget/test.pdf', 'changed');
        $this->get($base.'/quelle/'.$source->id.'.pdf')->assertNotFound();
        $plan->forceFill(['status' => 'draft'])->save();
        $this->get($base.'/quelle/'.$source->id.'.pdf')->assertNotFound();
    }
}
