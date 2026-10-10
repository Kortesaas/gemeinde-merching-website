<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\DomainRuleViolation;
use App\Http\Controllers\Controller;
use App\Models\BudgetGeneration;
use App\Models\BudgetPlan;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Content\BudgetWorkflow;
use App\Services\Content\DocumentStorage;
use App\Services\Content\RevisionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class BudgetController extends Controller
{
    public function upload(Request $request, int $record, BudgetWorkflow $workflow): RedirectResponse
    {
        $plan = BudgetPlan::findOrFail($record);
        abort_unless(Gate::allows('update', $plan) || Gate::allows('propose', $plan), 403);
        $data = $request->validate(['files' => ['required', 'array', 'min:1', 'max:'.config('budgets.max_sources')], 'files.*' => ['required', 'file', 'extensions:pdf', 'max:'.config('uploads.max_kilobytes')]]);
        try {
            $workflow->transaction(function () use ($data, $plan, $request, $workflow) {
                $workflow->lock($plan);
                abort_unless(Gate::allows('update', $plan) || Gate::allows('propose', $plan), 403);
                $actor = $request->user();
                assert($actor instanceof User);
                $offset = $plan->components()->count();
                foreach ($data['files'] as $index => $file) {
                    $source = $workflow->upload($plan, $file, $actor);
                    // Published content is untouched until a direct edit or proposal is approved.
                    if (! $plan->isPublicationLocked()) {
                        $plan->components()->create(['budget_source_id' => $source->getKey(), 'sort_order' => $offset + $index]);
                    }
                }
                if (! $plan->isPublicationLocked()) {
                    $workflow->markStale($plan);
                    app(RevisionService::class)->record($plan, $actor, 'PDF-Dateien hochgeladen');
                }
            });
        } catch (DomainRuleViolation $e) {
            throw $e->toValidationException();
        }

        return redirect()->route('admin.budget-plan.edit', $plan)->with('status', $plan->isPublicationLocked() ? 'PDF-Dateien hochgeladen. Im Inhalt oder Änderungsvorschlag auswählen und die Reihenfolge speichern.' : 'PDF-Dateien hinzugefügt. Reihenfolge prüfen und speichern, dann Gesamt-PDF erstellen.');
    }

    public function generate(Request $request, int $record, BudgetWorkflow $workflow): RedirectResponse
    {
        $plan = BudgetPlan::findOrFail($record);
        Gate::authorize('update', $plan);
        try {
            $actor = $request->user();
            assert($actor instanceof User);
            $workflow->transaction(function () use ($plan, $actor, $workflow) {
                $workflow->lock($plan);
                Gate::authorize('update', $plan);
                $generation = $workflow->generate($plan, $actor);
                app(RevisionService::class)->record($plan, $actor, 'Gesamt-PDF erstellt und Barrierefreiheitsstatus aktualisiert');

                return $generation;
            });
        } catch (DomainRuleViolation $e) {
            $plan->refresh()->forceFill(['generation_status' => 'failed', 'generation_error' => $e->getMessage()])->save();
            app(AuditLogger::class)->record('budget.generation_failed', $plan, ['reason' => $e->getMessage()], actor: $request->user());
            throw $e->toValidationException();
        }

        return redirect()->route('admin.budget-plan.edit', $plan)->with('status', 'Gesamt-PDF erstellt. Bitte herunterladen und die Barrierefreiheit separat prüfen.');
    }

    public function file(int $record, string $kind, int $file, BudgetWorkflow $workflow, DocumentStorage $storage): Response
    {
        $plan = BudgetPlan::withTrashed()->findOrFail($record);
        Gate::authorize('view', $plan);
        $asset = $kind === 'quelle' ? $plan->sources()->findOrFail($file) : BudgetGeneration::query()->where('budget_plan_id', $record)->findOrFail($file);
        $document = $workflow->document($asset);
        abort_unless($storage->exists($document), 404);

        return $storage->response($document)->setPrivate();
    }

    public function proof(int $record, int $publication): Response
    {
        $plan = BudgetPlan::withTrashed()->findOrFail($record);
        Gate::authorize('view', $plan);
        $receipt = $plan->publications()->with('generation')->findOrFail($publication);

        return response()->view('admin.budgets.proof', ['plan' => $plan, 'receipt' => $receipt, 'generation' => $receipt->generation])->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
