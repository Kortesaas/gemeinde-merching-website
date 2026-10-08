<?php

namespace App\Enums;

/**
 * Stored editorial status of publishable content.
 *
 * "Scheduled" and "Expired" are deliberately NOT stored: they follow from the
 * publication timestamps at request time (see PublicationState), so no cron
 * job has to flip a status when a date passes. Content whose visibility ended
 * stays "published" (shown as "Abgelaufen") and never silently becomes a draft.
 */
enum PublicationStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Entwurf',
            self::Published => 'Veröffentlicht (ab Veröffentlichungsdatum)',
            self::Archived => 'Archiviert',
        };
    }

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Published],
            self::Published => [self::Draft, self::Archived],
            self::Archived => [self::Published],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return $target === $this || in_array($target, $this->allowedTransitions(), true);
    }
}
