<?php

namespace App\Console\Commands;

use App\Services\Search\SearchIndexer;
use Illuminate\Console\Command;

class RebuildSearch extends Command
{
    protected $signature = 'search:rebuild';

    protected $description = 'Suchindex aus den aktuellen strukturierten Inhalten neu aufbauen';

    public function handle(SearchIndexer $index): int
    {
        $this->info($index->rebuild().' Einträge indexiert.');

        return self::SUCCESS;
    }
}
