<?php

namespace App\Support\Authorization;

/**
 * Permissions are defined in code (single source of truth) and synchronised to
 * the database with `php artisan permissions:sync`. Check them through gates,
 * policies or the `can:` middleware – never by comparing role names.
 *
 * Content permissions (articles, events, documents, …) are added together with
 * the respective content types in later phases.
 */
enum Permission: string
{
    /** Enter the backend at all. */
    case AccessAdmin = 'admin.access';

    /** Create, edit and deactivate employee accounts and assign roles. */
    case ManageUsers = 'users.manage';

    /** Read the audit log. */
    case ViewAuditLog = 'audit.view';

    public function label(): string
    {
        return match ($this) {
            self::AccessAdmin => 'Verwaltungsbereich aufrufen',
            self::ManageUsers => 'Benutzerkonten verwalten',
            self::ViewAuditLog => 'Protokoll einsehen',
        };
    }
}
