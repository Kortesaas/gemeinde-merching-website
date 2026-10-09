<?php

namespace App\Enums;

enum QualitySeverity: string
{
    case Error = 'error';
    case Warning = 'warning';
    case Recommendation = 'recommendation';

    public function label(): string
    {
        return match ($this) {
            self::Error => 'Fehler',self::Warning => 'Warnung',self::Recommendation => 'Empfehlung'
        };
    }
}
