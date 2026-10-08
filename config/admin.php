<?php

/*
| Employee backend (CMS) configuration.
|
| The URL prefix is configurable, but it is NOT a security measure: every
| backend route is protected by authentication, MFA and authorization.
*/

return [

    // URL prefix of the backend, e.g. https://www.merching.de/verwaltung
    'path' => trim((string) env('ADMIN_PATH', 'verwaltung'), '/'),

    'mfa' => [
        // Require TOTP two-factor authentication for every backend account.
        // Secure default everywhere; may only be disabled for local development.
        'required' => (bool) env('MFA_REQUIRED', true),

        // Issuer shown in authenticator apps.
        'issuer' => env('MFA_ISSUER', env('APP_NAME', 'Gemeinde Merching')),

        // Number of single-use recovery codes generated per account.
        'recovery_codes' => 10,

        // Seconds a user may take between password and second factor.
        'challenge_timeout' => 300,
    ],

    'session' => [
        // Absolute maximum duration of a backend login in minutes, independent of
        // activity. The idle timeout is SESSION_LIFETIME (config/session.php).
        'absolute_lifetime' => (int) env('ADMIN_SESSION_ABSOLUTE_LIFETIME', 600),
    ],

    'throttle' => [
        // Failed login attempts per account+client before a lockout.
        'login_attempts' => 5,
        // Failed login attempts per client (all accounts) before a lockout.
        'login_attempts_per_client' => 20,
        // Lockout duration in seconds.
        'lockout_seconds' => 300,
        // Wrong second-factor codes per pending login before a lockout.
        'mfa_attempts' => 5,
    ],
];
