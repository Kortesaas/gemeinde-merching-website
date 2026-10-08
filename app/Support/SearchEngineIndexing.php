<?php

namespace App\Support;

/**
 * Decides whether search engines may index the public website.
 *
 * Indexing requires BOTH a production environment AND PUBLIC_INDEXING=true, so
 * an unfinished, local or staging installation can never become indexable by
 * accident. The backend is never indexable (see AdminAreaHeaders).
 */
final class SearchEngineIndexing
{
    public static function allowed(): bool
    {
        return app()->isProduction() && config('site.public_indexing') === true;
    }

    public static function robotsDirective(): string
    {
        return self::allowed() ? 'index, follow' : 'noindex, nofollow';
    }
}
