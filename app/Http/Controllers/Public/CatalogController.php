<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\LifeSituation;
use App\Models\Page;
use App\Models\Service;
use App\Services\Content\PublicCatalog;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Symfony\Component\HttpFoundation\Response;

class CatalogController extends Controller
{
    /** @param array{title:string,kind:string} $section */
    public function show(Request $request, array $section, ?Page $model = null): Response
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:150'], 'archiv' => ['nullable', 'in:1'], 'online' => ['nullable', 'in:1'], 'category' => ['nullable', 'integer', 'min:1'], 'page' => ['nullable', 'integer', 'min:1', 'max:10000']]);
        $kind = $section['kind'];
        $catalog = app(PublicCatalog::class);
        $items = $kind === 'directory' ? $catalog->directory() : $catalog->items($kind, $request->boolean('archiv'));
        $categories = $items->map(function ($m): ?Category {
            if (! method_exists($m, 'category')) {
                return null;
            }
            $m->loadMissing('category');
            $category = $m->getRelation('category');

            return $category instanceof Category ? $category : null;
        })->filter()->unique('id')->sortBy('name')->mapWithKeys(fn ($c) => [$c->id => $c->name])->all();
        $term = trim($data['q'] ?? '');
        $items = $items->filter(fn ($m) => ($term === '' || mb_stripos($m->displayTitle(), $term) !== false)
            && (empty($data['category']) || $m->getAttribute('category_id') === (int) $data['category'])
            && (empty($data['online']) || ($m instanceof Service && $m->onlineService?->isPubliclyReachable())))->values();
        $groups = $kind === 'az' ? $items->groupBy(fn ($m) => mb_strtoupper(mb_substr(strtr($m->displayTitle(), ['Ä' => 'A', 'Ö' => 'O', 'Ü' => 'U', 'ä' => 'A', 'ö' => 'O', 'ü' => 'U']), 0, 1))) : collect();
        $records = new LengthAwarePaginator($items->forPage((int) ($data['page'] ?? 1), 20)->values(), $items->count(), 20, (int) ($data['page'] ?? 1), ['path' => $request->url(), 'query' => $request->query()]);
        $situations = $kind === 'services' ? LifeSituation::query()->visible()->orderBy('title')->get()->filter(fn ($m) => $m->publicPath() !== null) : collect();

        return response()->view('public.catalog', compact('section', 'kind', 'term', 'groups', 'records', 'situations', 'model', 'categories'));
    }
}
