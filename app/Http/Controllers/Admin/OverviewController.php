<?php

namespace App\Http\Controllers\Admin;

use App\Admin\ResourceRegistry;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class OverviewController extends Controller
{
    public function __invoke(Request $request): View
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:150'], 'type' => ['nullable', 'string', 'max:40'], 'page' => ['nullable', 'integer', 'min:1', 'max:1000']]);
        $items = collect();
        $types = [];
        foreach (ResourceRegistry::all() as $key => $resource) {
            if (! $request->user()?->can('viewAny', $resource->model())) {
                continue;
            }
            $types[$key] = $resource->pluralLabel();
            if (! empty($data['type']) && $data['type'] !== $key) {
                continue;
            }
            $query = $resource->query();
            $term = trim($data['q'] ?? '');
            if ($term !== '') {
                $query->where(function ($q) use ($resource, $term) {
                    foreach ($resource->searchColumns() as $column) {
                        $q->orWhere($column, 'like', '%'.addcslashes($term, '%_\\').'%');
                    }
                });
            }
            // Bounded editorial overview. Full pagination remains available per entity.
            foreach ($query->latest('updated_at')->limit(50)->get()->withRelationshipAutoloading() as $record) {
                $items->push(['resource' => $resource, 'record' => $record]);
            }
        }
        $items = $items->sortByDesc(fn ($row) => $row['record']->updated_at)->values();
        $page = (int) ($data['page'] ?? 1);
        $records = new LengthAwarePaginator($items->forPage($page, 25)->values(), $items->count(), 25, $page, ['path' => $request->url(), 'query' => $request->query()]);

        return view('admin.overview', ['records' => $records, 'types' => $types, 'search' => $data['q'] ?? '', 'type' => $data['type'] ?? '']);
    }
}
