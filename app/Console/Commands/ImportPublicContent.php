<?php

namespace App\Console\Commands;

use App\Services\Migration\PublicContentImporter;
use Illuminate\Console\Command;

class ImportPublicContent extends Command
{
    protected $signature = 'migration:import-public {--manifest=migration-source/prepared/manifest.json}';

    protected $description = 'Import the prepared public content into a separate local migration database';

    public function handle(PublicContentImporter $importer): int
    {
        $path = base_path((string) $this->option('manifest'));
        $manifest = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        $result = $importer->run($manifest);
        $this->line(json_encode(array_diff_key($result, ['review' => true]), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        $this->info('Detailed report: docs/migration/local/import-result.json');

        return self::SUCCESS;
    }
}
