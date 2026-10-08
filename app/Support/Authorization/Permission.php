<?php

namespace App\Support\Authorization;

/**
 * System permissions that are not tied to a content type. Content permissions
 * ("article.edit", …) are derived from ContentType × Ability.
 *
 * Permissions are defined in code (single source of truth) and synchronised to
 * the database with `php artisan permissions:sync`. Check them through gates,
 * policies or the `can:` middleware – never by comparing role names.
 */
enum Permission: string
{
    /** Enter the backend at all. */
    case AccessAdmin = 'admin.access';

    /** Read the audit log. */
    case ViewAuditLog = 'audit.view';

    public function label(): string
    {
        return match ($this) {
            self::AccessAdmin => 'Verwaltungsbereich aufrufen',
            self::ViewAuditLog => 'Protokoll einsehen',
        };
    }

    /**
     * Every permission name of the application (system + content).
     *
     * @return list<string>
     */
    public static function all(): array
    {
        $names = array_map(fn (self $p) => $p->value, self::cases());

        foreach (ContentType::cases() as $type) {
            array_push($names, ...$type->permissions());
        }

        return $names;
    }
}
