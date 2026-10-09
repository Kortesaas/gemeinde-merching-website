<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\Search\SearchIndexer;
use App\Services\Search\SearchStatistics;
use App\Services\Search\SiteSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class SearchController extends Controller
{
    public const LABELS = ['service' => 'Bürgerservice', 'article' => 'Meldung', 'event' => 'Veranstaltung', 'notice' => 'Bekanntmachung', 'document' => 'Dokument', 'person' => 'Ansprechpartner', 'department' => 'Abteilung', 'life-situation' => 'Lebenslage', 'location' => 'Ort', 'organization' => 'Organisation', 'page' => 'Seite', 'gallery' => 'Galerie', 'wahlperioden' => 'Gemeinderat'];

    public function index(Request $request, SiteSearch $search, SearchStatistics $statistics): Response
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:150'], 'type' => ['nullable', Rule::in(array_keys(SearchIndexer::TYPES))], 'page' => ['nullable', 'integer', 'min:1', 'max:10000']]);
        $phrase = trim($data['q'] ?? '');
        $type = $data['type'] ?? '';
        $page = (int) ($data['page'] ?? 1);
        $result = $search->search($phrase, $type !== '' ? [$type] : [], 20, ($page - 1) * 20);
        if ($phrase !== '' && config('search.statistics_enabled')) {
            $statistics->record($phrase, $result['total']);
        }
        $records = new LengthAwarePaginator($result['results'], $result['total'], 20, $page, ['path' => $request->url(), 'query' => $request->query()]);
        // Per-type counts for the result tabs come from one unfiltered pass over the same visible results.
        $all = $type === '' ? $result : $search->search($phrase, [], 1);
        $counts = $all['counts'];
        $alternatives = $search->alternatives($phrase);

        return response()->view('public.search', compact('phrase', 'type', 'records', 'counts', 'alternatives') + ['allTotal' => $all['total']])->header('X-Robots-Tag', 'noindex, follow');
    }

    public function suggestions(Request $request, SiteSearch $search): JsonResponse
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:150'], 'type' => ['nullable', Rule::in(array_keys(SearchIndexer::TYPES))]]);
        $result = $search->search(trim($data['q'] ?? ''), ! empty($data['type']) ? [$data['type']] : [], 6);

        return response()->json(['results' => array_map(fn ($r) => ['title' => $r['title'], 'url' => $r['url'], 'type' => self::LABELS[$r['type']] ?? 'Inhalt'], $result['results'])])->header('Cache-Control', 'no-store')->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
