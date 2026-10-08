<?php

namespace App\Enums;

enum NavigationMenu: string
{
    case Main = 'main';
    case Service = 'service';
    case Footer = 'footer';

    public function label(): string
    {
        return match ($this) {
            self::Main => 'Hauptnavigation',
            self::Service => 'Servicenavigation',
            self::Footer => 'Fußzeile',
        };
    }
}
