<?php

namespace App\Http\Controllers\Admin;

use App\Admin\ResourceRegistry;
use App\Enums\ProposalStatus;
use App\Http\Controllers\Controller;
use App\Models\ContentProposal;
use App\Models\Document;
use App\Models\Event;
use App\Models\Media;
use App\Support\Authorization\Ability;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $recent = collect();
        $reviewTypes = [];
        $attention = collect();
        foreach (ResourceRegistry::all() as $resource) {
            if ($resource->isPublishable() && $user?->can($resource->type()->permission(Ability::Publish))) {
                $class = $resource->model();
                $reviewTypes[] = (new $class)->getMorphClass();
            }
            if (! $user?->can('viewAny', $resource->model())) {
                continue;
            }
            foreach ($resource->query()->latest('updated_at')->limit(2)->get() as $record) {
                $recent->push(['record' => $record, 'resource' => $resource]);
            }
            if ($resource->isPublishable()) {
                foreach ($resource->query()->where('status', 'published')->where(fn ($q) => $q->where('publish_at', '>', now())->orWhereBetween('expires_at', [now(), now()->addDays(14)]))->orderBy('publish_at')->limit(2)->get() as $record) {
                    $attention->push(['record' => $record, 'resource' => $resource]);
                }
            }
        }
        $review = ContentProposal::query()->where('status', ProposalStatus::Submitted)->whereIn('proposable_type', $reviewTypes)
            ->when(config('admin.proposals.allow_self_approval') !== true, fn ($q) => $q->where(fn ($q) => $q->whereNull('author_id')->orWhere('author_id', '!=', $user?->getKey())))
            ->with(['author', 'proposable'])->orderBy('submitted_at')->limit(6)->get()->filter(fn ($p) => (bool) $user?->can('review', $p))->take(6);
        $quality = collect();
        foreach ([Document::class, Media::class, Event::class] as $class) {
            if (! $user?->can('viewAny', $class)) {
                continue;
            }
            $query = $class::query();
            if ($class === Document::class) {
                $query->where('accessibility_status', 'not_checked');
            }
            if ($class === Media::class) {
                $query->where('is_decorative', false)->where(fn ($q) => $q->whereNull('alt_text')->orWhere('alt_text', ''));
            }
            if ($class === Event::class) {
                $query->where('status', 'published')->where('operational_status', 'cancelled')->where('starts_at', '>=', now());
            }
            foreach ($query->latest('updated_at')->limit(4)->get() as $record) {
                $quality->push(['record' => $record, 'resource' => ResourceRegistry::forModel($record), 'message' => match ($class) {
                    Document::class => 'Warnung: Barrierefreiheit noch nicht geprüft.', Media::class => 'Fehler bei Veröffentlichung: Alternativtext fehlt.', default => 'Abgesagte bevorstehende Veranstaltung: Angaben prüfen.'
                }]);
            }
        }
        $mine = ContentProposal::query()->where('author_id', $user?->getKey())->with('proposable')->latest('updated_at')->limit(6)->get();

        return view('admin.dashboard', ['recent' => $recent->sortByDesc(fn ($r) => $r['record']->updated_at)->take(8), 'attention' => $attention->take(8), 'review' => $review, 'mine' => $mine, 'quality' => $quality]);
    }
}
