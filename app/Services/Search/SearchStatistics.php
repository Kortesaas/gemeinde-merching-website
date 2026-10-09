<?php

namespace App\Services\Search;

use Illuminate\Support\Facades\DB;

final class SearchStatistics
{
    public function record(string $phrase, int $resultCount): void
    {
        $this->prune();
        if (! config('search.statistics_enabled') || (int) config('search.retention_days') <= 0) {
            return;
        }
        $phrase = mb_substr(trim(preg_replace('/\s+/u', ' ', $phrase) ?? ''), 0, 150);
        // Discard obvious personal/contact identifiers; free-text can never be guaranteed anonymous.
        if ($phrase === '' || preg_match('/[@:\/]|\d{4,}|(?:\d[\s()+-]*){6,}/u', $phrase)) {
            return;
        }
        DB::table('search_statistics')->insert(['phrase' => $phrase, 'result_count' => max(0, $resultCount), 'result_clicked' => null, 'created_at' => now()]);
    }

    public function prune(): int
    {
        return DB::table('search_statistics')->where('created_at', '<', now()->subDays(max(0, (int) config('search.retention_days'))))->delete();
    }
}
