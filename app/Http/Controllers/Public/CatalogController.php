<?php

namespace App\Http\Controllers\Public;

use App\Enums\NavigationMenu;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\CouncilTerm;
use App\Models\Department;
use App\Models\Document;
use App\Models\ExternalResource;
use App\Models\LifeSituation;
use App\Models\Location;
use App\Models\Organization;
use App\Models\Page;
use App\Models\Person;
use App\Models\Service;
use App\Services\Content\PublicCatalog;
use App\Services\Navigation\NavigationManager;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;

class CatalogController extends Controller
{
    /** @param array{title:string,kind:string} $section */
    public function show(Request $request, array $section, ?Page $model = null): Response
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:150'], 'archiv' => ['nullable', 'in:1'], 'online' => ['nullable', 'in:1'], 'category' => ['nullable', 'integer', 'min:1'], 'page' => ['nullable', 'integer', 'min:1', 'max:10000']]);
        $kind = $section['kind'];
        $catalog = app(PublicCatalog::class);
        /** @var \Illuminate\Support\Collection<int, \Illuminate\Database\Eloquent\Model&\App\Contracts\Routable> $items */
        $items = match ($kind) {
            'directory' => $catalog->directory(),
            'organizations' => $catalog->directory()->filter(fn ($m) => $m instanceof Organization)->values(),
            default => $catalog->items($kind, $request->boolean('archiv')),
        };
        $categories = $items->map(function ($m): ?Category {
            if (! method_exists($m, 'category')) {
                return null;
            }
            $m->loadMissing('category');
            $category = $m->getRelation('category');

            return $category instanceof Category ? $category : null;
        })->filter()->unique('id')->sortBy('name')->mapWithKeys(fn ($c) => [$c->id => $c->name])->all();
        $term = trim($data['q'] ?? '');
        $items = $items->filter(fn ($m) => ($term === '' || mb_stripos($m->displayTitle(), $term) !== false || ($m instanceof Service && $m->aliases->contains(fn ($alias) => mb_stripos($alias->alias, $term) !== false)))
            && (empty($data['category']) || $m->getAttribute('category_id') === (int) $data['category'])
            && (empty($data['online']) || ($m instanceof Service && $m->onlineService?->isPubliclyReachable())))->values();
        $groups = $kind === 'az' ? $items->sortBy(fn ($m) => self::letter($m->getAttribute('sort_title') ?: $m->displayTitle()).mb_strtolower($m->getAttribute('sort_title') ?: $m->displayTitle()))->groupBy(fn ($m) => self::letter($m->getAttribute('sort_title') ?: $m->displayTitle()))->sortKeys() : collect();
        $page = (int) ($data['page'] ?? 1);
        $perPage = in_array($kind, ['directory', 'organizations', 'services'], true) ? max(1, $items->count()) : 20;
        $records = new LengthAwarePaginator($items->forPage($page, $perPage)->values(), $items->count(), $perPage, $page, ['path' => $request->url(), 'query' => $request->query()]);
        $situations = $kind === 'services' ? LifeSituation::query()->with('canonicalRoute')->visible()->orderBy('sort_order')->orderBy('title')->get()->filter(fn ($m) => $m->publicPath() !== null) : collect();
        $filtered = $term !== '' || ! empty($data['category']) || ! empty($data['online']);
        $extra = $kind === 'services' ? $this->serviceLanding($catalog) : [];

        return response()->view('public.catalog', compact('section', 'kind', 'term', 'groups', 'records', 'situations', 'model', 'categories', 'filtered') + $extra);
    }

    /** First letter for A–Z, with German umlauts folded to their base letter. */
    public static function letter(string $title): string
    {
        $first = mb_strtoupper(mb_substr(strtr(trim($title), ['Ä' => 'A', 'Ö' => 'O', 'Ü' => 'U', 'ä' => 'A', 'ö' => 'O', 'ü' => 'U']), 0, 1));

        return preg_match('/^[A-Z]$/', $first) === 1 ? $first : '#';
    }

    /** @return array<string, mixed> */
    private function serviceLanding(PublicCatalog $catalog): array
    {
        $letters = $catalog->items('az')->map(fn ($m) => self::letter($m->getAttribute('sort_title') ?: $m->displayTitle()))->unique()->values()->all();
        $online = ExternalResource::query()->visible()->whereIn('type', ['online_service', 'portal'])->orderBy('title')->get();
        $documents = $catalog->items('documents')->filter(fn ($d) => $d instanceof Document && ! $d->isInPublicArchive());
        $documentCategories = $documents->groupBy(fn (Document $d): string => $d->category !== null ? $d->category->name : 'Weitere Dokumente')->map->count()->sortKeys();
        $people = Person::query()->where('is_active', true)->whereHas('services', fn ($q) => $q->visible())->with(['departments' => fn ($q) => $q->where('is_active', true)])->orderBy('sort_order')->limit(6)->get();

        return [
            'shortcuts' => app(NavigationManager::class)->tree(NavigationMenu::Service),
            'letters' => $letters,
            'portal' => $online->first(fn ($r) => $r->getRawOriginal('type') === 'portal'),
            'onlineServices' => $online->filter(fn ($r) => $r->getRawOriginal('type') === 'online_service')->values(),
            'documentCategories' => $documentCategories,
            'people' => $people,
        ];
    }

    /**
     * Directory entries grouped for the overview, in a stable reading order.
     *
     * @param  iterable<mixed>  $records
     * @return array<string, Collection<int, mixed>>
     */
    public static function directoryGroups(iterable $records): array
    {
        $labels = [Department::class => 'Verwaltung', Person::class => 'Ansprechpersonen', Location::class => 'Orte und Einrichtungen', Organization::class => 'Vereine, Gewerbe und Gastronomie', CouncilTerm::class => 'Gemeinderat'];
        $groups = [];
        foreach ($labels as $class => $label) {
            $groups[$label] = collect($records)->filter(fn ($m) => $m instanceof $class)->values();
        }

        return array_filter($groups, fn ($group) => $group->isNotEmpty());
    }
}
