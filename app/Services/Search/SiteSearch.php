<?php

namespace App\Services\Search;

use App\Models\SearchSynonym;
use App\Support\Routing\PublicPath;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class SiteSearch
{
    public function __construct(private readonly SearchVisibility $visibility) {}

    /**
     * @param list<string> $types
     * @return array{total:int,counts:array<string,int>,results:list<array{type:string,id:int,title:string,summary:string,url:string,score:float}>} */
    public function search(string $phrase, array $types = [], int $limit = 20, int $offset = 0): array
    {
        if (array_diff($types, array_keys(SearchIndexer::TYPES)) !== []) {
            throw new InvalidArgumentException('Unbekannter Inhaltstyp.');
        }
        $phrase = mb_substr(trim(preg_replace('/\s+/u', ' ', $phrase) ?? ''), 0, 150);
        if ($phrase === '') {
            return ['total' => 0, 'counts' => [], 'results' => []];
        }
        $terms = $this->expand($phrase);
        $boolean = $this->booleanQuery($terms);
        $match = 'MATCH(title,summary,keywords,body) AGAINST (? IN BOOLEAN MODE)';
        $q = DB::table('search_entries')->select('*')->selectRaw($match.' AS relevance', [$boolean]);
        if ($types !== []) {
            $q->whereIn('content_type', $types);
        }
        $q->where(function ($q) use ($terms, $match, $boolean) {
            $q->whereRaw($match.' > 0', [$boolean]);
            foreach ($terms as $term) {
                $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term).'%';
                foreach (['title', 'summary', 'keywords', 'body'] as $column) {
                    $q->orWhereRaw($column." LIKE ? ESCAPE '!'", [$like]);
                }
            }
        });
        $results = [];
        foreach ($q->orderByDesc('relevance')->orderBy('id')->cursor() as $entry) {
            $class = SearchIndexer::TYPES[$entry->content_type] ?? null;
            $model = $class !== null ? $class::query()->find((int) $entry->content_id) : null;
            $path = $model !== null ? $this->visibility->path($model) : null;
            if ($path === null) {
                continue;
            }
            $score = (float) $entry->relevance;
            foreach ($terms as $term) {
                if (mb_strtolower($entry->title) === mb_strtolower($term)) {
                    $score += 100;
                } elseif (mb_stripos($entry->title, $term) !== false) {
                    $score += 20;
                }
                if (mb_stripos($entry->keywords, $term) !== false) {
                    $score += 10;
                }
            }
            $results[] = ['type' => $entry->content_type, 'id' => (int) $entry->content_id, 'title' => $entry->title, 'summary' => $entry->summary, 'url' => PublicPath::absoluteUrl($path), 'score' => $score];
        }
        usort($results, fn ($a, $b) => $b['score'] <=> $a['score'] ?: [$a['type'], $a['id']] <=> [$b['type'], $b['id']]);

        $counts = [];
        foreach ($results as $result) {
            $counts[(string) $result['type']] = ($counts[(string) $result['type']] ?? 0) + 1;
        }

        return ['total' => count($results), 'counts' => $counts, 'results' => array_slice($results, max(0, $offset), max(1, min(100, $limit)))];
    }

    /** @param list<string> $terms */
    private function booleanQuery(array $terms): string
    {
        $words = [];
        foreach ($terms as $term) {
            $plain = preg_replace('/[^\pL\pN ]/u', ' ', $term) ?? '';
            foreach (preg_split('/\s+/u', trim($plain)) ?: [] as $word) {
                if ($word !== '') {
                    $words[] = $word.'*';
                }
            }
        }

        return implode(' ', array_unique($words));
    }

    /** @return list<string> */
    /**
     * Managed synonyms that are also searched for a phrase (shown as a hint).
     *
     * @return list<string>
     */
    public function alternatives(string $phrase): array
    {
        $phrase = trim($phrase);

        return $phrase === '' ? [] : array_values(array_filter($this->expand($phrase), fn ($term) => mb_strtolower($term) !== mb_strtolower($phrase)));
    }

    /** @return list<string> */
    private function expand(string $phrase): array
    {
        $terms = [$phrase];
        foreach (SearchSynonym::query()->where('is_active', true)->orderBy('id')->get() as $synonym) {
            $group = array_values(array_filter(array_map('trim', [$synonym->phrase, ...explode(';', $synonym->alternatives)])));
            foreach ($group as $term) {
                if (mb_strtolower($phrase) === mb_strtolower($term)) {
                    $terms = [...$terms, ...$group];
                    break;
                }
            }
        }

        return array_slice(array_values(array_unique($terms)), 0, 20);
    }
}
