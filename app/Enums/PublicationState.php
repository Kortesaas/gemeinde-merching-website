<?php

namespace App\Enums;

/**
 * Effective, time-dependent state of publishable content, derived from the
 * stored status and the UTC publication timestamps.
 */
enum PublicationState: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Published = 'published';
    case Expired = 'expired';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Entwurf',
            self::Scheduled => 'Geplant',
            self::Published => 'Öffentlich',
            self::Expired => 'Abgelaufen',
            self::Archived => 'Archiviert',
        };
    }
}
