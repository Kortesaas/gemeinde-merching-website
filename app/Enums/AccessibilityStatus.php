<?php

namespace App\Enums;

/**
 * Accessibility of a document. New uploads are always "not checked" – the CMS
 * never claims accessibility that nobody has verified.
 */
enum AccessibilityStatus: string
{
    case NotChecked = 'not_checked';
    case Accessible = 'accessible';
    case PartiallyAccessible = 'partially_accessible';
    case NotAccessible = 'not_accessible';
    case AlternativeProvided = 'alternative_provided';

    public function label(): string
    {
        return match ($this) {
            self::NotChecked => 'Nicht geprüft',
            self::Accessible => 'Barrierefrei',
            self::PartiallyAccessible => 'Teilweise barrierefrei',
            self::NotAccessible => 'Nicht barrierefrei',
            self::AlternativeProvided => 'Barrierefreie Alternative vorhanden',
        };
    }
}
