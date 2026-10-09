<?php

namespace App\Support\Content;

use App\Support\Routing\PublicPath;

/** Production metadata URLs never depend on the request host or local APP_URL. */
final class SeoUrl
{
    public static function path(string $path): string
    {
        return rtrim((string) config('seo.origin'), '/').PublicPath::toUrl(PublicPath::withoutTrailingSlash($path));
    }
}
