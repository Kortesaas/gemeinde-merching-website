<?php

/*
| Upload policy (foundation for future documents, images and galleries).
| See docs/security.md#uploads for the full strategy.
|
| An upload is only accepted when BOTH its extension is listed here AND the
| MIME type detected from the file *content* is allowed for that extension.
| The client-supplied filename and MIME type are never trusted.
|
| Deliberately NOT allowed: SVG (can contain scripts), HTML, PHP and any other
| executable or active content, archives.
*/

return [

    // Storage disk for original uploads. Lives outside public/ (storage/app/private).
    'disk' => env('UPLOADS_DISK', 'local'),

    // Base directory on that disk.
    'directory' => 'uploads',

    // Maximum size per file in kilobytes (also check PHP upload_max_filesize).
    'max_kilobytes' => (int) env('UPLOADS_MAX_KILOBYTES', 20480),

    // extension => allowed MIME types detected from file content
    'types' => [
        'pdf' => ['application/pdf'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'webp' => ['image/webp'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation'],
        'odt' => ['application/vnd.oasis.opendocument.text'],
        'ods' => ['application/vnd.oasis.opendocument.spreadsheet'],
    ],

];
