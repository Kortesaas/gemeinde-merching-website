<?php

namespace App\Enums;

/**
 * One directory model for clubs, businesses and gastronomy.
 */
enum OrganizationType: string
{
    case Club = 'verein';
    case Business = 'gewerbe';
    case Gastronomy = 'gastronomie';
    case Other = 'sonstige';

    public function label(): string
    {
        return match ($this) {
            self::Club => 'Verein',
            self::Business => 'Gewerbe',
            self::Gastronomy => 'Gastronomie',
            self::Other => 'Sonstige',
        };
    }
}
