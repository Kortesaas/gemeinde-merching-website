<?php

namespace App\Enums;

enum OnlineServiceMode: string
{
    case NotSpecified = 'not_specified';
    case Unavailable = 'unavailable';
    case Information = 'information';
    case Application = 'application';
    case Appointment = 'appointment';

    public function label(): string
    {
        return match ($this) {
            self::NotSpecified => 'Nicht angegeben',
            self::Unavailable => 'Kein Online-Dienst',
            self::Information => 'Information / Vorbereitung online',
            self::Application => 'Online-Antrag',
            self::Appointment => 'Online-Terminvereinbarung',
        };
    }
}
