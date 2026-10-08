<?php

namespace App\Enums;

/**
 * Which kind of content a category belongs to. Categories are managed in the
 * CMS (one table), never hard-coded.
 */
enum CategoryContext: string
{
    case Article = 'article';
    case Event = 'event';
    case Document = 'document';
    case PublicNotice = 'notice';
    case Service = 'service';
    case Organization = 'organization';

    public function label(): string
    {
        return match ($this) {
            self::Article => 'Artikel',
            self::Event => 'Veranstaltungen',
            self::Document => 'Dokumente',
            self::PublicNotice => 'Bekanntmachungen',
            self::Service => 'Bürgerservice',
            self::Organization => 'Verzeichnis',
        };
    }
}
