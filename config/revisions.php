<?php

/*
| Content revisions (editorial history) – deliberately separate from the
| audit log and its retention (config/audit.php).
|
| Retention is a PENDING POLICY DECISION. Default: keep all revisions.
| `php artisan revisions:prune` applies the settings below.
*/

return [

    // Delete revisions older than this many days (null = keep forever).
    'retention_days' => env('REVISION_RETENTION_DAYS') !== null ? (int) env('REVISION_RETENTION_DAYS') : null,

    // Always keep at least this many latest revisions per record.
    'keep_latest' => (int) env('REVISION_KEEP_LATEST', 20),

];
