<?php

namespace App\Enums;

enum ExternalResourceType: string
{
    case OnlineService = 'online_service';
    case Portal = 'portal';
    case Form = 'form';
    case Authority = 'authority';
    case Map = 'map';
    case Information = 'information';

    public function label(): string
    {
        return match ($this) {
            self::OnlineService => 'Online-Dienst',
            self::Portal => 'Portal',
            self::Form => 'Externes Formular',
            self::Authority => 'Andere Behörde',
            self::Map => 'Karte',
            self::Information => 'Information',
        };
    }
}
