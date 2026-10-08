<?php

namespace App\Enums;

enum LocationType: string
{
    case Administration = 'verwaltung';
    case Facility = 'einrichtung';
    case Recycling = 'entsorgung';
    case Leisure = 'freizeit';
    case Venue = 'veranstaltungsort';
    case Other = 'sonstige';

    public function label(): string
    {
        return match ($this) {
            self::Administration => 'Verwaltung',
            self::Facility => 'Einrichtung',
            self::Recycling => 'Entsorgung / Wertstoffe',
            self::Leisure => 'Freizeit & Natur',
            self::Venue => 'Veranstaltungsort',
            self::Other => 'Sonstiger Ort',
        };
    }
}
