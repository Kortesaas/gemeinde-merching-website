<?php

/*
| HTTP security configuration. See docs/security.md for the reasoning.
*/

$appHost = parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST) ?: 'localhost';

return [

    // Redirect every plain-HTTP request to HTTPS on the canonical host
    // (default: production only).
    'force_https' => (bool) env('FORCE_HTTPS', env('APP_ENV') === 'production'),

    // Host names the application answers to (protects generated links, e.g. in
    // password-reset mails, against Host-header injection). Not enforced locally.
    // Requests for any other host get HTTP 400.
    'trusted_hosts' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('TRUSTED_HOSTS', $appHost)),
    ))),

    // Alias host names that are permanently redirected (path and query kept)
    // to the canonical host of APP_URL, e.g. "gemeinde-merching.de" ->
    // "https://www.gemeinde-merching.de". Empty = no host redirects. These
    // hosts are trusted automatically.
    'redirect_hosts' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('REDIRECT_HOSTS', '')),
    ))),

    // Reverse proxies whose X-Forwarded-* headers may be trusted. Empty means
    // none (goneo serves PHP directly). Comma-separated IPs/CIDRs or "*".
    'trusted_proxies' => env('TRUSTED_PROXIES'),

    'hsts' => [
        // Only sent in production over HTTPS.
        'enabled' => (bool) env('HSTS_ENABLED', true),
        'max_age' => (int) env('HSTS_MAX_AGE', 31536000),
        // Affects every subdomain of the domain – only enable after checking
        // that all subdomains (mail, other services) support HTTPS.
        'include_subdomains' => (bool) env('HSTS_INCLUDE_SUBDOMAINS', false),
    ],

    // Content-Security-Policy applied to every HTML response. All CSS/JS is
    // self-hosted and loaded from files; inline scripts/styles are not allowed.
    'csp' => [
        'default-src' => ["'self'"],
        'script-src' => ["'self'"],
        'style-src' => ["'self'"],
        'img-src' => ["'self'", 'data:'],
        'font-src' => ["'self'"],
        'connect-src' => ["'self'"],
        'media-src' => ["'self'"],
        'worker-src' => ["'self'"],
        'manifest-src' => ["'self'"],
        'frame-src' => ["'none'"],
        'object-src' => ["'none'"],
        'base-uri' => ["'self'"],
        'form-action' => ["'self'"],
        'frame-ancestors' => ["'none'"],
    ],

    'permissions_policy' => 'accelerometer=(), autoplay=(), browsing-topics=(), camera=(), display-capture=(), '
        .'geolocation=(), gyroscope=(), magnetometer=(), microphone=(), midi=(), payment=(), '
        .'publickey-credentials-get=(self), usb=(), xr-spatial-tracking=()',
];
