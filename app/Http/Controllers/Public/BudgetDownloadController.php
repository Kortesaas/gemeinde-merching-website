<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\BudgetPlan;
use App\Services\Content\BudgetWorkflow;
use App\Services\Content\DocumentStorage;
use App\Services\Content\SourceOnlyBudget;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Downloads use the last successful publication, never draft associations or on-demand merging. */
class BudgetDownloadController extends Controller
{
    public function __invoke(Request $request, BudgetWorkflow $workflow, DocumentStorage $storage): StreamedResponse
    {
        $year = (int) $request->route('year');
        $source = $request->route('source') !== null ? (int) $request->route('source') : null;
        $budget = $request->route('budget');
        if ($budget !== null) {
            $plan = BudgetPlan::query()->where('year', $year)->findOrFail((int) $budget);
        } else {
            // Retain old single-package URLs only when they identify exactly one public package.
            // Never silently serve a different topic after another package is published.
            $plans = BudgetPlan::query()->where('year', $year)->get()->filter(fn ($p) => $p->isPubliclyReachable() && $p->publications()->exists());
            abort_unless($plans->count() === 1, 404);
            $plan = $plans->sole();
        }
        abort_unless($plan->isPubliclyReachable(), 404);
        $receipt = $plan->publications()->with('generation')->first();
        abort_if($receipt === null, 404);
        if ($receipt->getAttribute('source_only')) {
            abort_if($source === null, 404);
            $file = app(SourceOnlyBudget::class)->source($plan, $receipt, $source);
            $document = $workflow->document($file);
            $response = $storage->response($document);
            $response->headers->set('X-Robots-Tag', 'noindex');

            return $response;
        }
        $file = $receipt->generation;
        abort_if($file === null, 404);
        if ($source !== null) {
            abort_unless($receipt->show_components && collect($file->sources)->contains(fn ($s) => (int) $s['id'] === $source), 404);
            $file = $plan->sources()->findOrFail($source);
        }
        $document = $workflow->document($file);
        abort_unless($storage->exists($document), 404);
        $response = $storage->response($document);
        $response->headers->set('X-Robots-Tag', 'noindex');

        return $response;
    }
}
