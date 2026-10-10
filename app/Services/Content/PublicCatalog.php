<?php

namespace App\Services\Content;

use App\Contracts\Routable;
use App\Models;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/** Live visibility is evaluated before pagination; no publication-time cache. */
final class PublicCatalog
{
    /** @var array<string,class-string<Model&Routable>> */
    public const TYPES = ['services' => Models\Service::class, 'az' => Models\Service::class, 'articles' => Models\Article::class, 'events' => Models\Event::class, 'notices' => Models\PublicNotice::class, 'documents' => Models\Document::class, 'budgets' => Models\BudgetPlan::class];

    public function sectionPath(string $kind): string
    {
        foreach ((array) config('public.sections', []) as $path => $section) {
            if ($section['kind'] === $kind) {
                return $path;
            }
        }

        return '/suche';
    }

    /** @return Collection<int, Model&Routable> */
    public function items(string $kind, bool $archive = false): Collection
    {
        $class = self::TYPES[$kind];
        $query = $class::query()->with($kind === 'budgets' ? ['canonicalRoute', 'publications.generation'] : ['category', 'canonicalRoute']);
        if ($class === Models\Document::class) {
            $query->with(['replacedBy.canonicalRoute', 'accessibleAlternative.canonicalRoute']);
        }
        if ($class === Models\Event::class) {
            $query->with(['location']);
        }
        if ($class === Models\Article::class) {
            $query->with(['media']);
        }
        if ($class === Models\Service::class) {
            $query->with(['departments', 'onlineService', 'aliases', 'contacts']);
        }
        if (in_array($kind, ['events', 'documents', 'budgets'], true)) {
            $query->where(fn (Builder $q) => $q->visible()->orWhere(fn (Builder $q) => $q->publicArchive()));
        } elseif ($archive && in_array($kind, ['articles', 'notices'], true)) {
            $query->publicArchive();
        } else {
            $query->visible();
        }
        if ($kind === 'budgets') {
            $query->orderBy('year', 'desc')->orderBy('topic')->orderBy('title');
        }
        $query->orderBy(in_array($kind, ['services', 'az'], true) ? 'title' : ($kind === 'events' ? 'starts_at' : 'publish_at'), in_array($kind, ['services', 'az', 'events'], true) ? 'asc' : 'desc');

        /** @var Collection<int, Model&Routable> $records */
        $records = $query->get()->toBase();

        return $records->filter(function (Model $model) use ($archive): bool {
            if ($model instanceof Models\Event) {
                $past = $model->endsAtForArchiving()->lessThanOrEqualTo(now());
                if ($archive !== ($past || $model->isInPublicArchive())) {
                    return false;
                }
            }
            if ($model instanceof Models\Document) {
                // A private replacement must not alter the public listing or reveal its existence.
                $replacement = $model->replacedBy;
                $superseded = $replacement !== null && $replacement->isPubliclyReachable() && app(DocumentStorage::class)->exists($replacement);

                return $archive === ($model->isInPublicArchive() || $superseded) && $model->isPubliclyReachable() && app(DocumentStorage::class)->exists($model);
            }

            if ($model instanceof Models\BudgetPlan && $model->publications->isEmpty()) {
                return false;
            }

            return $model->isPubliclyReachable() && $model->publicPath() !== null;
        })->values();
    }

    /** @return Collection<int, Models\Department|Models\Person|Models\Organization|Models\Location|Models\CouncilTerm> */
    public function directory(): Collection
    {
        /** @var Collection<int, Models\Department|Models\Person|Models\Organization|Models\Location|Models\CouncilTerm> $items */
        $items = collect();
        $relations = [Models\Department::class => ['canonicalRoute', 'location'], Models\Person::class => ['departments'], Models\Organization::class => ['canonicalRoute', 'category'], Models\Location::class => ['canonicalRoute', 'mapResource']];
        foreach ($relations as $class => $with) {
            $items = $items->concat($class::query()->with($with)->where('is_active', true)->orderBy('sort_order')->get());
        }
        $items = $items->concat(Models\CouncilTerm::query()->with('canonicalRoute')->get()->filter(fn ($m) => $m->isPubliclyReachable() && $m->publicPath() !== null));

        return $items->filter(fn ($m) => $m->isPubliclyReachable())->values();
    }
}
