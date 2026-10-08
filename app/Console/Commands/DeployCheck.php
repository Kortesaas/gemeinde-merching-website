<?php

namespace App\Console\Commands;

use App\Support\SearchEngineIndexing;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Verifies the security-relevant configuration of a production installation.
 * Run after every deployment (see docs/deployment-goneo.md).
 */
#[Signature('deploy:check')]
#[Description('Verify that the production configuration is safe')]
class DeployCheck extends Command
{
    public function handle(): int
    {
        $appUrl = (string) config('app.url');

        $checks = [
            'PHP version >= 8.4' => version_compare(PHP_VERSION, '8.4.0', '>='),
            'APP_ENV=production' => app()->isProduction(),
            'APP_DEBUG=false (effective)' => config('app.debug') === false,
            'APP_KEY is set' => config('app.key') !== null && config('app.key') !== '',
            'APP_URL uses https://' => str_starts_with($appUrl, 'https://'),
            'Session cookie is Secure' => config('session.secure') === true,
            'Session driver is database' => config('session.driver') === 'database',
            'HTTPS redirect enabled' => config('security.force_https') === true,
            'MFA required for backend' => config('admin.mfa.required') === true,
            'Mail is not the log driver' => config('mail.default') !== 'log',
            'Queue runs synchronously (no worker)' => config('queue.default') === 'sync',
            'Compiled frontend assets present' => is_file(public_path('build/manifest.json')),
            'No Vite dev-server "hot" file' => ! is_file(public_path('hot')),
            'storage/ is writable' => is_writable(storage_path('framework')) && is_writable(storage_path('logs')),
            'Database connection works' => $this->databaseWorks(),
        ];

        $failed = 0;
        foreach ($checks as $label => $ok) {
            $ok ? $this->components->twoColumnDetail($label, '<fg=green>OK</>')
                : $this->components->twoColumnDetail($label, '<fg=red>FAILED</>');
            $failed += $ok ? 0 : 1;
        }

        $this->newLine();
        $this->components->twoColumnDetail(
            'Search-engine indexing (PUBLIC_INDEXING)',
            SearchEngineIndexing::allowed() ? '<fg=yellow>ENABLED</>' : 'disabled (noindex)',
        );

        if ($failed > 0) {
            $this->components->error("{$failed} check(s) failed.");

            return self::FAILURE;
        }

        $this->components->info('All checks passed.');

        return self::SUCCESS;
    }

    private function databaseWorks(): bool
    {
        try {
            DB::connection()->getPdo();

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
