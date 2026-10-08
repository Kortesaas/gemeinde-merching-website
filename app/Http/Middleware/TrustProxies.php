<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;

/**
 * Reads trusted reverse proxies from config('security.trusted_proxies') so the
 * setting also works with a cached configuration.
 */
class TrustProxies extends Middleware
{
    /**
     * @return array<int, string>|string|null
     */
    protected function proxies()
    {
        $configured = config('security.trusted_proxies');

        if (! is_string($configured) || trim($configured) === '') {
            return parent::proxies();
        }

        return trim($configured) === '*'
            ? '*'
            : array_values(array_filter(array_map('trim', explode(',', $configured))));
    }
}
