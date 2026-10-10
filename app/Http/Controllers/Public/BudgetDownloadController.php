<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\BudgetPlan;
use App\Services\Content\BudgetWorkflow;
use App\Services\Content\DocumentStorage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Downloads use the last successful publication, never draft associations or on-demand merging. */
class BudgetDownloadController extends Controller
{
    public function __invoke(int $year, BudgetWorkflow $workflow, DocumentStorage $storage, ?int $source = null): StreamedResponse
    {
        $plan = BudgetPlan::query()->where('year', $year)->firstOrFail();
        abort_unless($plan->isPubliclyReachable(), 404);
        $receipt = $plan->publications()->with('generation')->first();
        abort_if($receipt === null, 404);
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
