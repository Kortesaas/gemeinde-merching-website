<?php

return [
    // Presentation endpoints are fallback-only: managed routes/redirects always win.
    'sections' => [
        '/buergerservice' => ['title' => 'Bürgerservice', 'kind' => 'services'],
        '/buergerservice/a-z' => ['title' => 'Bürgerservice von A bis Z', 'kind' => 'az'],
        '/aktuelles' => ['title' => 'Aktuelles', 'kind' => 'articles'],
        '/veranstaltungen' => ['title' => 'Veranstaltungen', 'kind' => 'events'],
        '/bekanntmachungen' => ['title' => 'Bekanntmachungen', 'kind' => 'notices'],
        '/dokumente' => ['title' => 'Dokumente und Formulare', 'kind' => 'documents'],
        '/verzeichnisse' => ['title' => 'Ansprechpartner und Einrichtungen', 'kind' => 'directory'],
    ],
    'homepage' => ['services' => true, 'news' => true, 'events' => true, 'online' => true, 'contact' => true],
    'wappen' => 'resources/images/wappen-merching-prototype.png',
];
