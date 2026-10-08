<?php

/*
| Session configuration. See docs/security.md#sessions.
|
| - Sessions are only started for routes in the "web" middleware group (the
|   backend and, later, stateful public forms). Anonymous visitors reading the
|   public website never get a session or cookie.
| - Server-side storage in MySQL ("database" driver, no Redis). The handler is
|   replaced in AppServiceProvider so no IP address / user agent is stored.
| - Production cookies are always Secure + HttpOnly (enforced in
|   AppServiceProvider) and use the "__Host-" prefix.
*/

$secureCookie = (bool) env('SESSION_SECURE_COOKIE', env('APP_ENV') === 'production');

return [

    'driver' => env('SESSION_DRIVER', 'database'),

    // Idle timeout in minutes. The absolute backend timeout is configured in
    // config/admin.php (session.absolute_lifetime).
    'lifetime' => (int) env('SESSION_LIFETIME', 60),

    // End the session when the browser is closed (shared office computers).
    'expire_on_close' => (bool) env('SESSION_EXPIRE_ON_CLOSE', true),

    // Encrypt session payloads at rest in the database.
    'encrypt' => (bool) env('SESSION_ENCRYPT', true),

    'files' => storage_path('framework/sessions'),

    'connection' => env('SESSION_CONNECTION'),

    'table' => env('SESSION_TABLE', 'sessions'),

    'store' => env('SESSION_STORE'),

    // Chance per request to garbage-collect expired sessions (no cron needed).
    'lottery' => [2, 100],

    // "__Host-" cookies are only accepted over HTTPS, for path "/" and without
    // a Domain attribute, which prevents subdomains from overwriting them.
    'cookie' => env(
        'SESSION_COOKIE',
        ($secureCookie && ! env('SESSION_DOMAIN') ? '__Host-' : '').'merching_session',
    ),

    'path' => env('SESSION_PATH', '/'),

    'domain' => env('SESSION_DOMAIN'),

    'secure' => $secureCookie,

    'http_only' => true,

    // "lax" plus CSRF tokens protects state-changing requests while still
    // allowing links from e-mails (e.g. password reset) to work normally.
    'same_site' => env('SESSION_SAME_SITE', 'lax'),

    'partitioned' => false,

    'serialization' => 'json',

];
