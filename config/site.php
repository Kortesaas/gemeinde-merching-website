<?php

return [

    /*
    | Search-engine indexing of the public website.
    |
    | Indexing is only ever allowed when APP_ENV=production AND this flag is
    | explicitly enabled. Local, testing and staging installations are always
    | "noindex, nofollow". The backend is always "noindex" regardless.
    */
    'public_indexing' => (bool) env('PUBLIC_INDEXING', false),

];
