<?php

namespace App\Session;

use Illuminate\Session\DatabaseSessionHandler;

/**
 * Database session handler that does not store the client's IP address or
 * user agent (Laravel's default handler does). Privacy by default.
 */
class PrivacyDatabaseSessionHandler extends DatabaseSessionHandler
{
    /**
     * @param  array<string, mixed>  $payload
     * @return $this
     */
    protected function addRequestInformation(&$payload)
    {
        return $this;
    }
}
