<?php

namespace App\Support\Authorization;

/**
 * Backend roles and their default permissions (least privilege).
 *
 * The final content permissions per role are defined in a later phase; for now
 * every role may enter the backend and only administrators manage accounts.
 */
enum Role: string
{
    case Administrator = 'administrator';
    case Chefredaktion = 'chefredaktion';
    case Fachbereichsredaktion = 'fachbereichsredaktion';
    case Veranstaltungsredaktion = 'veranstaltungsredaktion';
    case Reviewer = 'pruefer';

    public function label(): string
    {
        return match ($this) {
            self::Administrator => 'Administration',
            self::Chefredaktion => 'Chefredaktion',
            self::Fachbereichsredaktion => 'Fachbereichsredaktion',
            self::Veranstaltungsredaktion => 'Veranstaltungsredaktion',
            self::Reviewer => 'Prüfung',
        };
    }

    /**
     * @return list<Permission>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::Administrator => Permission::cases(),
            self::Chefredaktion,
            self::Fachbereichsredaktion,
            self::Veranstaltungsredaktion,
            self::Reviewer => [Permission::AccessAdmin],
        };
    }
}
