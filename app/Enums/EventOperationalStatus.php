<?php

namespace App\Enums;

enum EventOperationalStatus: string
{
    case Scheduled = 'scheduled';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Findet statt',
            self::Cancelled => 'Abgesagt',
        };
    }
}
