<?php

return [

    /*
    | Time zone for every date/time citizens and editors see or enter
    | (display, forms, scheduled publishing and expiry). Internal and database
    | timestamps are always UTC (config/app.php). See App\Support\SiteTime.
    */
    'timezone' => env('SITE_TIMEZONE', 'Europe/Berlin'),

    /*
    | Search-engine indexing of the public website.
    |
    | Indexing is only ever allowed when APP_ENV=production AND this flag is
    | explicitly enabled. Local, testing and staging installations are always
    | "noindex, nofollow". The backend is always "noindex" regardless.
    */
    'public_indexing' => (bool) env('PUBLIC_INDEXING', false),

];
