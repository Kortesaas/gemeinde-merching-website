<?php

namespace App\Http\Controllers\Admin;

use App\Admin\ResourceRegistry;
use App\Enums\AccessibilityStatus;
use App\Enums\ProposalStatus;
use App\Enums\PublicationStatus;
use App\Enums\QualitySeverity;
use App\Http\Controllers\Controller;
use App\Models\ContentProposal;
use App\Models\Document;
use App\Models\Event;
use App\Models\Media;
use App\Services\Quality\QualityChecks;
use App\Support\Authorization\Ability;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Editorial start page. Every query is bounded and limited to entity types
 * the signed-in user may view; policies still run on every linked action.
 */
class DashboardController extends Controller
{
    /** Configuration and system records are not editorial activity. */
    private const SYSTEM = ['navigation', 'redirect', 'kategorien', 'schlagwoerter', 'search-synonym', 'site-settings', 'contact-route'];

    public function __invoke(Request $request, QualityChecks $checks): View
    {
        $user = $request->user();
        $recent = collect();
        $reviewTypes = [];
        $attention = collect();
        $scheduledCount = 0;
        $expiringCount = 0;
        $drafts = collect();
        foreach (ResourceRegistry::all() as $resource) {
            if ($resource->isPublishable() && $user?->can($resource->type()->permission(Ability::Publish))) {
                $class = $resource->model();
                $reviewTypes[] = (new $class)->getMorphClass();
            }
            if (! $user?->can('viewAny', $resource->model())) {
                continue;
            }
            if (! in_array($resource->key(), self::SYSTEM, true)) {
                foreach ($resource->query()->latest('updated_at')->limit(3)->get() as $record) {
                    $recent->push(['record' => $record, 'resource' => $resource]);
                }
            }
            if ($resource->isPublishable()) {
                $scheduled = $resource->query()->where('status', PublicationStatus::Published)->where('publish_at', '>', now());
                $expiring = $resource->query()->where('status', PublicationStatus::Published)->where('publish_at', '<=', now())->whereBetween('expires_at', [now(), now()->addDays(14)]);
                $scheduledCount += (clone $scheduled)->count();
                $expiringCount += (clone $expiring)->count();
                foreach ($scheduled->orderBy('publish_at')->limit(3)->get() as $record) {
                    $attention->push(['record' => $record, 'resource' => $resource, 'kind' => 'scheduled', 'at' => $record->getAttribute('publish_at')]);
                }
                foreach ($expiring->orderBy('expires_at')->limit(3)->get() as $record) {
                    $attention->push(['record' => $record, 'resource' => $resource, 'kind' => 'expiring', 'at' => $record->getAttribute('expires_at')]);
                }
                foreach ($resource->query()->where('status', PublicationStatus::Draft)->latest('updated_at')->limit(2)->get() as $record) {
                    $drafts->push(['record' => $record, 'resource' => $resource]);
                }
            }
        }
        $reviewQuery = ContentProposal::query()->where('status', ProposalStatus::Submitted)->whereIn('proposable_type', $reviewTypes)
            ->when(config('admin.proposals.allow_self_approval') !== true, fn ($q) => $q->where(fn ($q) => $q->whereNull('author_id')->orWhere('author_id', '!=', $user?->getKey())));
        $review = (clone $reviewQuery)->with(['author', 'proposable'])->orderBy('submitted_at')->limit(6)->get()->filter(fn ($p) => (bool) $user?->can('review', $p))->take(6);

        $quality = collect();
        // Automatic publication checks on the newest drafts surface blocking errors early.
        foreach ($drafts->sortByDesc(fn ($row) => $row['record']->updated_at)->take(6) as $row) {
            foreach ($checks->inspect($row['record']) as $issue) {
                if ($issue->severity === QualitySeverity::Error) {
                    $quality->push(['record' => $row['record'], 'resource' => $row['resource'], 'severity' => $issue->severity, 'message' => $issue->message]);
                }
            }
        }
        if ($user?->can('viewAny', Media::class)) {
            foreach (Media::query()->where('is_decorative', false)->where(fn ($q) => $q->whereNull('alt_text')->orWhere('alt_text', ''))->latest('updated_at')->limit(3)->get() as $record) {
                $quality->push(['record' => $record, 'resource' => ResourceRegistry::forModel($record), 'severity' => QualitySeverity::Error, 'message' => 'Alternativtext fehlt – das Bild kann so nicht veröffentlicht werden.']);
            }
        }
        $uncheckedDocuments = 0;
        if ($user?->can('viewAny', Document::class)) {
            $uncheckedDocuments = Document::query()->where('accessibility_status', AccessibilityStatus::NotChecked)->count();
            foreach (Document::query()->where('accessibility_status', AccessibilityStatus::NotChecked)->latest('updated_at')->limit(3)->get() as $record) {
                $quality->push(['record' => $record, 'resource' => ResourceRegistry::forModel($record), 'severity' => QualitySeverity::Warning, 'message' => 'Barrierefreiheit noch nicht geprüft.']);
            }
        }
        $events = collect();
        if ($user?->can('viewAny', Event::class)) {
            $events = Event::query()->with('location')->where('status', PublicationStatus::Published)->where('starts_at', '>=', now()->startOfDay())->orderBy('starts_at')->limit(5)->get();
            foreach ($events->where('operational_status.value', 'cancelled') as $record) {
                $quality->push(['record' => $record, 'resource' => ResourceRegistry::forModel($record), 'severity' => QualitySeverity::Recommendation, 'message' => 'Abgesagt – Hinweistext und Verknüpfungen prüfen.']);
            }
        }
        $mine = ContentProposal::query()->where('author_id', $user?->getKey())->with('proposable')->latest('updated_at')->limit(5)->get();

        return view('admin.dashboard', [
            'recent' => $recent->sortByDesc(fn ($r) => $r['record']->updated_at)->take(6),
            'attention' => $attention->sortBy(fn ($r) => $r['at'])->take(6),
            'review' => $review,
            'mine' => $mine,
            'quality' => $quality->unique(fn ($row) => $row['record']->getMorphClass().$row['record']->getKey())
                ->sortBy(fn ($row) => match ($row['severity']) {
                    QualitySeverity::Error => 0, QualitySeverity::Warning => 1, default => 2
                })->take(7),
            'events' => $events,
            'counts' => ['review' => (clone $reviewQuery)->count(), 'scheduled' => $scheduledCount, 'expiring' => $expiringCount, 'unchecked' => $uncheckedDocuments],
        ]);
    }
}
