<?php

namespace App\Enums;

/**
 * Change-proposal workflow:
 *
 *   draft ──submit──▶ submitted ──apply──▶ applied
 *     │                  │  └──reject──▶ rejected
 *     └──withdraw──┬─────┘
 *                  ▼
 *              withdrawn
 */
enum ProposalStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Applied = 'applied';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Entwurf (noch nicht eingereicht)',
            self::Submitted => 'Zur Prüfung eingereicht',
            self::Applied => 'Freigegeben und veröffentlicht',
            self::Rejected => 'Abgelehnt',
            self::Withdrawn => 'Zurückgezogen',
        };
    }

    public function isOpen(): bool
    {
        return $this === self::Draft || $this === self::Submitted;
    }
}
