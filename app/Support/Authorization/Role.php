<?php

namespace App\Support\Authorization;

/**
 * Backend roles and their DEFAULT permissions (least privilege).
 *
 * These defaults are a starting point for review with the municipality (see
 * docs/content-model.md#permission-matrix). Code checks permissions, never
 * role names.
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
     * @return list<string>
     */
    public function permissions(): array
    {
        $access = [Permission::AccessAdmin->value];

        return match ($this) {
            // Everything, including permanent deletion, accounts and the audit log.
            self::Administrator => Permission::all(),

            // Full editorial control without permanent deletion and accounts.
            self::Chefredaktion => [...$access, ...self::grant(
                array_filter(ContentType::cases(), fn (ContentType $t) => $t !== ContentType::User),
                [Ability::View, Ability::Create, Ability::Edit, Ability::Publish, Ability::Archive, Ability::Delete],
            )],

            // Prepares content; publishing is done by Chefredaktion.
            self::Fachbereichsredaktion => [
                ...$access,
                ...self::grant(self::editorialTypes(), [Ability::View, Ability::Create, Ability::Edit]),
                ...self::grant([ContentType::Taxonomy, ContentType::Redirect, ContentType::Navigation], [Ability::View]),
            ],

            // Owns the event calendar end-to-end.
            self::Veranstaltungsredaktion => [
                ...$access,
                ...self::grant([ContentType::Event], [Ability::View, Ability::Create, Ability::Edit, Ability::Publish, Ability::Archive, Ability::Delete]),
                ...self::grant([ContentType::Location, ContentType::Organization, ContentType::Document, ContentType::ExternalResource], [Ability::View, Ability::Create, Ability::Edit]),
                ...self::grant([ContentType::Person, ContentType::Department, ContentType::Taxonomy], [Ability::View]),
            ],

            // Read-only for now; prepared for a later approval workflow.
            self::Reviewer => [
                ...$access,
                ...self::grant(array_filter(ContentType::cases(), fn (ContentType $t) => $t !== ContentType::User), [Ability::View]),
            ],
        };
    }

    /**
     * @return list<ContentType>
     */
    private static function editorialTypes(): array
    {
        return [
            ContentType::Article, ContentType::Event, ContentType::Document, ContentType::ExternalResource,
            ContentType::PublicNotice, ContentType::Service, ContentType::LifeSituation, ContentType::Page,
            ContentType::Person, ContentType::Department, ContentType::Organization, ContentType::Location,
        ];
    }

    /**
     * @param  iterable<ContentType>  $types
     * @param  list<Ability>  $abilities
     * @return list<string>
     */
    private static function grant(iterable $types, array $abilities): array
    {
        $permissions = [];
        foreach ($types as $type) {
            foreach ($abilities as $ability) {
                if (in_array($ability, $type->abilities(), true)) {
                    $permissions[] = $type->permission($ability);
                }
            }
        }

        return $permissions;
    }
}
