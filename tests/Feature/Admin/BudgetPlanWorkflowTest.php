<?php

namespace Tests\Feature\Admin;

use App\Admin\Resources\BudgetPlanResource;
use App\Contracts\QualityCheck;
use App\Enums\AccessibilityStatus;
use App\Enums\PublicationStatus;
use App\Enums\QualitySeverity;
use App\Exceptions\DomainRuleViolation;
use App\Models\BudgetGeneration;
use App\Models\BudgetPlan;
use App\Models\BudgetSource;
use App\Models\User;
use App\Services\Content\BudgetWorkflow;
use App\Services\Content\ProposalService;
use App\Services\Content\RevisionService;
use App\Services\Quality\ContentBasics;
use App\Services\Quality\ContentComposition;
use App\Services\Quality\QualityChecks;
use App\Services\Quality\ReferencedAssets;
use App\Services\Routing\RouteManager;
use App\Support\Authorization\Role;
use App\Support\Content\QualityIssue;
use Database\Seeders\Demo\DemoFiles;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use setasign\Fpdi\Fpdi;
use Tests\TestCase;

class BudgetPlanWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $publisher;

    private BudgetWorkflow $workflow;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake((string) config('uploads.disk'));
        $this->publisher = $this->createUser();
        $this->workflow = app(BudgetWorkflow::class);
    }

    private function package(int $year = 2026): BudgetPlan
    {
        return $this->workflow->transaction(function () use ($year) {
            $plan = BudgetPlan::create(['year' => $year, 'title' => 'Haushaltsplan '.$year]);
            foreach (['Satzung', 'Finanzplan'] as $position => $label) {
                $source = $this->workflow->upload($plan, UploadedFile::fake()->createWithContent($label.'.pdf', DemoFiles::pdf($label, ['Originalquelle '.$position])), $this->publisher);
                $plan->components()->create(['budget_source_id' => $source->id, 'sort_order' => $position]);
            }
            app(RouteManager::class)->assign($plan, '/haushaltsplaene/'.$year);

            return $plan;
        });
    }

    private function save(BudgetPlan $plan, array $data = []): BudgetPlan
    {
        return app(BudgetPlanResource::class)->save($plan, $data, Request::create('/'), $this->publisher);
    }

    public function test_multiple_uploads_use_secure_storage_and_preserve_original_files(): void
    {
        $plan = BudgetPlan::create(['year' => 2026, 'title' => 'Haushaltsplan 2026']);
        $this->actingAsAdmin($this->publisher)->post(route('admin.budget-plan.upload', $plan), ['files' => [UploadedFile::fake()->createWithContent('Satzung.pdf', DemoFiles::pdf('Satzung', ['A'])), UploadedFile::fake()->createWithContent('Plan.pdf', DemoFiles::pdf('Plan', ['B']))]])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(2, $plan->sources()->count());
        $this->assertSame(2, $plan->components()->count());
        $source = $plan->sources()->first();
        $this->assertSame(hash('sha256', Storage::disk(config('uploads.disk'))->get($source->file_path)), $source->sha256);
        $this->assertSame('stale', $plan->refresh()->generation_status);
        $this->assertNotNull($plan->revisions()->first());
    }

    public function test_invalid_file_in_batch_rolls_back_all_uploads_and_files(): void
    {
        $plan = BudgetPlan::create(['year' => 2026, 'title' => 'Haushaltsplan 2026']);
        $this->actingAsAdmin($this->publisher)->post(route('admin.budget-plan.upload', $plan), ['files' => [UploadedFile::fake()->createWithContent('okay.pdf', DemoFiles::pdf('Okay', [])), UploadedFile::fake()->createWithContent('bad.pdf', '<?php echo 1;')]])->assertSessionHasErrors();
        $this->assertSame(0, BudgetSource::count());
        $this->assertSame([], Storage::disk(config('uploads.disk'))->allFiles());
    }

    public function test_publication_generates_once_in_order_and_download_never_merges(): void
    {
        $plan = $this->package();
        $ids = $plan->components()->pluck('budget_source_id')->all();
        $this->save($plan, ['status' => 'published', 'components__present' => 1, 'components' => [['sort_order' => 0, 'budget_source_id' => $ids[1]], ['sort_order' => 1, 'budget_source_id' => $ids[0]]]]);
        $generation = $plan->refresh()->currentGeneration;
        $this->assertSame([$ids[1], $ids[0]], array_column($generation->sources, 'id'));
        $this->assertSame(2, $generation->page_count);
        $pdf = new Fpdi;
        $this->assertSame(2, $pdf->setSourceFile(Storage::disk(config('uploads.disk'))->path($generation->file_path)));
        $this->get(route('public.budget.download', 2026))->assertOk()->assertHeader('Content-Type', 'application/pdf')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get(route('public.budget.download', 2026))->assertOk();
        $this->assertSame(1, BudgetGeneration::count());
        $this->save($plan);
        $this->assertSame(1, $plan->publications()->count());
    }

    public function test_order_change_is_stale_and_regeneration_resets_combined_accessibility(): void
    {
        $plan = $this->package();
        $this->workflow->transaction(fn () => $this->workflow->generate($plan, $this->publisher));
        $this->save($plan, ['accessibility_status' => 'accessible']);
        $this->assertSame(AccessibilityStatus::Accessible, $plan->refresh()->accessibility_status);
        $ids = $plan->components()->pluck('budget_source_id')->all();
        $this->save($plan, ['components__present' => 1, 'components' => [['sort_order' => 0, 'budget_source_id' => $ids[1]], ['sort_order' => 1, 'budget_source_id' => $ids[0]]]]);
        $this->assertSame('stale', $plan->refresh()->generation_status);
        $this->assertSame(AccessibilityStatus::NotChecked, $plan->accessibility_status);
        $this->save($plan, ['status' => 'published', 'accessibility_status' => 'accessible']);
        $this->assertSame(2, BudgetGeneration::count());
        $this->assertSame(AccessibilityStatus::NotChecked, $plan->refresh()->accessibility_status);
    }

    public function test_invalid_pdf_blocks_publication_and_shows_cms_failure(): void
    {
        $plan = $this->package();
        $source = $this->workflow->transaction(fn () => $this->workflow->upload($plan, UploadedFile::fake()->createWithContent('broken.pdf', "%PDF-1.4\n%%EOF\n"), $this->publisher));
        $plan->components()->delete();
        $plan->components()->create(['budget_source_id' => $source->id, 'sort_order' => 0]);
        $this->actingAsAdmin($this->publisher)->post(route('admin.budget-plan.generate', $plan))->assertSessionHasErrors('components');
        $this->assertSame('failed', $plan->refresh()->generation_status);
        try {
            $this->save($plan, ['status' => 'published']);
            $this->fail('Publication must fail.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('components', $e->errors());
        }
        $this->assertSame(PublicationStatus::Draft, $plan->refresh()->status);
        $this->assertSame(0, $plan->publications()->count());
        $this->get(route('public.budget.download', 2026))->assertNotFound();
    }

    public function test_changed_source_checksum_blocks_merge(): void
    {
        $plan = $this->package();
        $source = $plan->sources()->first();
        Storage::disk(config('uploads.disk'))->put($source->file_path, DemoFiles::pdf('Tampered', []));
        $this->expectException(DomainRuleViolation::class);
        $this->workflow->transaction(fn () => $this->workflow->generate($plan, $this->publisher));
    }

    public function test_packages_cannot_use_another_years_sources_or_duplicate_a_source(): void
    {
        $plan = $this->package();
        $other = $this->package(2027);
        foreach ([$other->sources()->first()->id, $plan->sources()->first()->id] as $id) {
            try {
                $this->save($plan, ['components__present' => 1, 'components' => [['budget_source_id' => $id, 'sort_order' => 0], ['budget_source_id' => $id, 'sort_order' => 1]]]);
                $this->fail('Invalid sources must be rejected.');
            } catch (ValidationException) {
                $this->assertSame(2, $plan->components()->count());
            }
        }
    }

    public function test_proposal_order_stays_private_until_approval_and_restore_rebuilds(): void
    {
        $plan = $this->package();
        $this->save($plan, ['status' => 'published']);
        $original = $plan->revisions()->first();
        $originalGeneration = $plan->refresh()->current_generation_id;
        $editor = $this->createUser(Role::Fachbereichsredaktion);
        $proposals = app(ProposalService::class);
        $proposal = $proposals->create($plan, $editor);
        $ids = $plan->components()->pluck('budget_source_id')->all();
        $proposals->update($proposal, app(BudgetPlanResource::class), ['topic' => 'Nachtrag', 'components__present' => 1, 'components' => [['budget_source_id' => $ids[1], 'sort_order' => 0], ['budget_source_id' => $ids[0], 'sort_order' => 1]]], Request::create('/'), $editor);
        $this->assertSame($originalGeneration, $plan->refresh()->current_generation_id);
        $this->assertSame($ids, $plan->components()->pluck('budget_source_id')->all());
        $this->assertSame('Haushaltsplan', $plan->topic);
        $proposals->submit($proposal, $editor);
        $proposals->apply($proposal, $this->publisher, false);
        $this->assertNotSame($originalGeneration, $plan->refresh()->current_generation_id);
        $this->assertSame(array_reverse($ids), array_column($plan->currentGeneration->sources, 'id'));
        $this->assertSame('Nachtrag', $plan->topic);
        $this->assertSame('Nachtrag', $plan->publications()->first()->topic);
        app(RevisionService::class)->restore($original, $this->publisher);
        $this->assertSame($ids, array_column($plan->refresh()->currentGeneration->sources, 'id'));
        $this->assertSame(3, $plan->publications()->count());
        $this->assertSame('Haushaltsplan', $plan->topic);
    }

    public function test_public_upload_preserves_published_files_until_selection_is_approved(): void
    {
        $plan = $this->package();
        $this->save($plan, ['status' => 'published']);
        $editor = $this->createUser(Role::Fachbereichsredaktion);
        $this->actingAsAdmin($editor)->post(route('admin.budget-plan.upload', $plan), ['files' => [UploadedFile::fake()->createWithContent('New.pdf', DemoFiles::pdf('New', []))]])->assertSessionHasNoErrors();
        $this->assertSame(3, $plan->sources()->count());
        $this->assertSame(2, $plan->components()->count());
        $this->assertSame(1, BudgetGeneration::count());
        $this->actingAsAdmin($editor)->post(route('admin.budget-plan.generate', $plan))->assertForbidden();
    }

    public function test_receipt_is_immutable_and_uses_stored_hashes_names_and_actor(): void
    {
        $plan = $this->package();
        $this->save($plan, ['status' => 'published']);
        $receipt = $plan->publications()->first();
        $name = $this->publisher->name;
        $this->publisher->update(['name' => 'Changed later']);
        $this->save($plan, ['title' => 'Changed title']);
        $response = $this->actingAsAdmin($this->publisher)->get(route('admin.budget-plan.proof', [$plan->id, $receipt->id]));
        $response->assertOk()->assertSee('Haushaltsplan 2026')->assertSee($name)->assertSee($receipt->generation->sha256)->assertSee('Satzung.pdf')->assertSee('Finanzplan.pdf')->assertSee('data-print', false)->assertDontSee('Changed title');
        $this->assertSame(2, $plan->publications()->count());
    }

    public function test_draft_and_scheduled_files_and_proof_are_not_public(): void
    {
        $plan = $this->package();
        $this->get(route('public.budget.download', 2026))->assertNotFound();
        $this->save($plan, ['status' => 'published', 'publish_at' => now()->addDay()->format('Y-m-d\TH:i')]);
        $this->get(route('public.budget.download', 2026))->assertNotFound();
        $this->get('/haushaltsplaene')->assertOk()->assertDontSee('Haushaltsplan 2026');
        $this->get(route('admin.budget-plan.proof', [$plan->id, $plan->publications()->first()->id]))->assertRedirect(route('admin.login'));
    }

    public function test_public_listing_has_one_entry_per_package_and_components_are_opt_in(): void
    {
        $plan = $this->package();
        $this->save($plan, ['status' => 'published']);
        $this->get('/haushaltsplaene')->assertOk()->assertSee('Haushaltsplan 2026')->assertSee(route('public.budget.package.download', [2026, $plan->id]))->assertCookieMissing(config('session.cookie'));
        $this->get('/haushaltsplaene/2026')->assertOk()->assertSee('Gesamt-PDF herunterladen')->assertDontSee('Satzung.pdf');
        $source = $plan->sources()->first();
        $this->get(route('public.budget.source', [2026, $source->id]))->assertNotFound();
        $this->save($plan, ['show_components' => 1]);
        $this->get(route('public.budget.source', [2026, $source->id]))->assertOk();
        $this->get('/haushaltsplaene/2026')->assertSee('Satzung.pdf');
    }

    public function test_read_only_and_unverified_mfa_users_cannot_upload_generate_or_print(): void
    {
        $plan = $this->package();
        $viewer = $this->createUser(Role::Reviewer);
        $this->actingAsAdmin($viewer)->post(route('admin.budget-plan.upload', $plan))->assertForbidden();
        $this->actingAsAdmin($viewer)->post(route('admin.budget-plan.generate', $plan))->assertForbidden();
        $this->actingAsAdmin($this->publisher, false)->post(route('admin.budget-plan.generate', $plan))->assertRedirect();
    }

    public function test_source_page_and_size_limits_block_publication(): void
    {
        $plan = $this->package();
        foreach (['max_sources' => 1, 'max_pages' => 1, 'max_total_bytes' => 1] as $key => $limit) {
            $old = config('budgets.'.$key);
            config(['budgets.'.$key => $limit]);
            try {
                $this->save($plan, ['status' => 'published']);
                $this->fail('Limit must block publishing.');
            } catch (ValidationException) {
                $this->assertSame(PublicationStatus::Draft, $plan->refresh()->status);
            }
            config(['budgets.'.$key => $old]);
        }
    }

    public function test_multipage_landscape_source_retains_page_dimensions(): void
    {
        $plan = $this->package();
        $pdf = new \FPDF;
        $pdf->AddPage('L', 'A4');
        $pdf->SetFont('Helvetica', '', 12);
        $pdf->Cell(100, 20, 'Landscape source page 1');
        $pdf->AddPage('P', 'A4');
        $pdf->Cell(100, 20, 'Portrait source page 2');
        $source = $this->workflow->transaction(fn () => $this->workflow->upload($plan, UploadedFile::fake()->createWithContent('Mixed.pdf', $pdf->Output('S')), $this->publisher));
        $plan->components()->create(['budget_source_id' => $source->id, 'sort_order' => 2]);
        $this->save($plan, ['status' => 'published']);
        $generation = $plan->refresh()->currentGeneration;
        $reader = new Fpdi;
        $this->assertSame(4, $reader->setSourceFile(Storage::disk(config('uploads.disk'))->path($generation->file_path)));
        $third = $reader->getTemplateSize($reader->importPage(3));
        $fourth = $reader->getTemplateSize($reader->importPage(4));
        $this->assertSame('L', $third['orientation']);
        $this->assertSame('P', $fourth['orientation']);
        $this->assertEqualsWithDelta(297, $third['width'], .1);
        $this->assertSame(2, $source->refresh()->page_count);
    }

    public function test_a_late_publication_failure_removes_generated_file_and_rolls_back_receipt(): void
    {
        $plan = $this->package();
        $originalFiles = Storage::disk(config('uploads.disk'))->allFiles();
        // Bind this concrete check set, since ordinary checks are resolved afresh.
        $checks = new QualityChecks(new ContentBasics, new ReferencedAssets, new ContentComposition);
        $checks->add(new class implements QualityCheck
        {
            public function supports(Model $model): bool
            {
                return $model instanceof BudgetPlan;
            }

            public function inspect(Model $model): array
            {
                return [new QualityIssue('test.block', QualitySeverity::Error, 'Blocked after generation', 'components')];
            }
        });
        $this->app->instance(QualityChecks::class, $checks);
        try {
            $this->save($plan, ['status' => 'published']);
            $this->fail('Publication must roll back.');
        } catch (ValidationException) {
            $this->assertSame(0, BudgetGeneration::count());
            $this->assertSame(0, $plan->publications()->count());
            $this->assertSame($originalFiles, Storage::disk(config('uploads.disk'))->allFiles());
            $this->assertSame(PublicationStatus::Draft, $plan->refresh()->status);
        }
    }

    public function test_already_generated_package_detects_source_tampering_before_republication(): void
    {
        $plan = $this->package();
        $this->save($plan, ['status' => 'published']);
        $source = $plan->sources()->first();
        Storage::disk(config('uploads.disk'))->put($source->file_path, DemoFiles::pdf('Tampered later', []));
        try {
            $this->save($plan);
            $this->fail('Changed original must block republishing.');
        } catch (ValidationException) {
            $this->assertSame(1, $plan->publications()->count());
        }
    }

    public function test_budget_search_finds_published_year_but_hides_draft(): void
    {
        $plan = $this->package();
        $this->package(2027);
        $this->save($plan, ['status' => 'published']);
        $this->get('/suche?q=Haushaltsplan&type=budget-plan')->assertOk()->assertSee('2026')->assertDontSee('2027');
    }

    public function test_republishing_after_withdrawal_creates_a_new_publication_record(): void
    {
        $plan = $this->package();
        $this->save($plan, ['status' => 'published']);
        $first = $plan->publications()->first()->id;
        $this->save($plan, ['status' => 'draft']);
        $this->save($plan, ['status' => 'published']);
        $this->assertSame(2, $plan->publications()->count());
        $this->assertNotSame($first, $plan->publications()->first()->id);
        $this->assertSame(1, BudgetGeneration::count());
    }

    public function test_multiple_packages_per_year_are_independent_and_never_ambiguously_downloaded(): void
    {
        $plan = $this->package();
        $this->save($plan, ['status' => 'published']);
        $other = BudgetPlan::create(['year' => 2026, 'topic' => 'Nachtrag', 'title' => 'Nachtrag zum Gemeindehaushalt 2026', 'show_components' => true]);
        $source = $this->workflow->upload($other, UploadedFile::fake()->createWithContent('Nachtrag.pdf', DemoFiles::pdf('Nachtrag', ['Separates Paket'])), $this->publisher);
        $other->components()->create(['budget_source_id' => $source->id, 'sort_order' => 0]);
        app(RouteManager::class)->assign($other, '/haushaltsplaene/nachtrag-2026');
        $this->save($other, ['status' => 'published', 'show_components' => true]);
        $this->assertSame(2, BudgetPlan::where('year', 2026)->count());
        $this->get('/haushaltsplaene')->assertSee('Nachtrag zum Gemeindehaushalt 2026')->assertSee('Haushaltsplan 2026')
            ->assertSee(route('public.budget.package.download', [2026, $plan->id]))->assertSee(route('public.budget.package.download', [2026, $other->id]));
        $a = $this->get(route('public.budget.package.download', [2026, $plan->id]))->assertOk();
        $b = $this->get(route('public.budget.package.download', [2026, $other->id]))->assertOk();
        $this->assertNotSame(hash('sha256', $a->streamedContent()), hash('sha256', $b->streamedContent()));
        $this->get(route('public.budget.download', 2026))->assertNotFound();
        $this->get(route('public.budget.package.source', [2026, $other->id, $source->id]))->assertOk();
        $this->get(route('public.budget.package.source', [2026, $plan->id, $source->id]))->assertNotFound();
        $this->get(route('public.budget.package.download', [2027, $other->id]))->assertNotFound();
        $first = $other->publications()->first();
        $this->assertSame('Nachtrag', $first->topic);
        $this->save($other, ['topic' => 'Berichtigung']);
        $this->assertSame('Nachtrag', $first->fresh()->topic);
        $this->assertSame('Berichtigung', $other->publications()->first()->topic);
    }

    public function test_topic_is_in_revisions_and_legacy_snapshots_restore_the_default(): void
    {
        $plan = $this->package();
        $revision = app(RevisionService::class)->record($plan, $this->publisher);
        $this->save($plan, ['topic' => 'Berichtigung']);
        $this->assertSame('Berichtigung', app(RevisionService::class)->snapshot($plan)['attributes']['topic']);
        app(RevisionService::class)->restore($revision, $this->publisher);
        $this->assertSame('Haushaltsplan', $plan->fresh()->topic);
        $snapshot = app(RevisionService::class)->snapshot($plan);
        unset($snapshot['attributes']['topic']);
        $plan->update(['topic' => 'Nachtrag']);
        app(RevisionService::class)->applySnapshot($plan, $snapshot);
        $this->assertSame('Haushaltsplan', $plan->fresh()->topic);
    }
}
