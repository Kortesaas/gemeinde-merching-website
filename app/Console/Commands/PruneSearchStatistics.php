<?php

namespace App\Console\Commands;

use App\Services\Search\SearchStatistics;
use Illuminate\Console\Command;

class PruneSearchStatistics extends Command
{
    protected $signature = 'search:prune-statistics';

    protected $description = 'Abgelaufene Suchstatistik löschen';

    public function handle(SearchStatistics $statistics): int
    {
        $this->info($statistics->prune().' Einträge gelöscht.');

        return self::SUCCESS;
    }
}
