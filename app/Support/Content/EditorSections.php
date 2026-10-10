<?php

namespace App\Support\Content;

use App\Admin\Fields\Field;

final class EditorSections
{
    /**
     * Group fields by the employee's task, keeping declaration order within each group.
     *
     * @param  list<Field>  $fields
     * @return array<string,list<Field>>
     */
    public static function group(array $fields, ?string $resource = null): array
    {
        $groups = [];
        foreach ($fields as $field) {
            $name = $field->name;
            $section = match (true) {
                $resource === 'site-settings' && str_starts_with($name, 'greeting_') => 'Grußwort',
                $resource === 'site-settings' && $name === 'homepage_media_id' => 'Startseitenbild',
                $resource === 'site-settings' && in_array($name, ['postal_address', 'legal_contact']) => 'Postanschrift & rechtlicher Kontakt',
                $resource === 'site-settings' && in_array($name, ['default_seo_title', 'default_meta_description']) => 'Suchmaschinen',
                $resource === 'site-settings' => 'Gemeinde & zentrale Kontakte',
                $resource === 'budget-plan' && in_array($name, ['year', 'title', 'description']) => 'Haushaltsjahr & Inhalt',
                $resource === 'budget-plan' && in_array($name, ['components', 'show_components']) => 'PDF-Paket',
                $resource === 'navigation' && in_array($name, ['public_route_id', 'external_resource_id', 'url']) => 'Linkziel',
                $resource === 'navigation' && in_array($name, ['menu', 'label', 'parent_id']) => 'Menü & Beschriftung',
                str_starts_with($name, 'seo_') || $name === 'meta_description' => 'Suchmaschinen',
                in_array($name, ['sort_order', 'is_active', 'is_featured', 'auto_archive', 'recurrence_rule', 'template']) => 'Weitere Optionen',
                in_array($name, ['sort_title', 'aliases']) => 'Suchbegriffe',
                in_array($name, ['starts_at', 'ends_at', 'all_day', 'operational_status', 'schedule_notice', 'venue', 'organizer_name', 'registration_url'])
                    || ($resource === 'event' && in_array($name, ['location_id', 'organization_id'])) => 'Termin & Veranstaltungsort',
                in_array($name, ['prerequisites', 'required_items', 'processing_duration', 'important_notice']) => 'Ablauf & Unterlagen',
                in_array($name, ['fees', 'online_service_mode', 'online_service_resource_id']) => 'Gebühren & Online-Dienst',
                in_array($name, ['accessibility_status', 'accessibility_notes', 'accessible_alternative_id']) => 'Barrierefreiheit',
                in_array($name, ['document_date', 'valid_from', 'valid_until', 'year', 'replaces_document_id']) => 'Datum & Gültigkeit',
                in_array($name, ['phone', 'fax', 'email', 'room', 'availability', 'public_notes', 'contact_name', 'website', 'links']) => 'Kontakt & Erreichbarkeit',
                in_array($name, ['street', 'postal_code', 'city', 'opening_hours', 'accessibility_note', 'latitude', 'longitude', 'map_resource_id']) => 'Adresse & Öffnungszeiten',
                in_array($name, ['focal_x', 'focal_y']) => 'Bildzuschnitt',
                in_array($name, ['alt_text', 'is_decorative', 'copyright', 'creator', 'caption', 'language']) => 'Bildbeschreibung & Rechte',
                in_array($name, ['media', 'items']) => 'Bilder & Medien',
                $name === 'blocks' => 'Inhaltsbausteine',
                in_array($name, ['contacts', 'people', 'departments', 'department_id', 'location_id', 'organization_id', 'contact_person_id', 'relatedServices', 'lifeSituations', 'memberships', 'committeeMemberships', 'council_term_id']) => 'Zuständigkeiten & Zuordnungen',
                default => 'Inhalt',
            };
            // A document's language describes the file, not image rights.
            if ($resource === 'document' && $name === 'language') {
                $section = 'Inhalt';
            }
            $groups[$section][] = $field;
        }

        // Keep the essential fields first and advanced settings last.
        $last = ['Weitere Optionen', 'Bildzuschnitt', 'Suchbegriffe', 'Suchmaschinen'];
        foreach ($last as $section) {
            if (isset($groups[$section])) {
                $group = $groups[$section];
                unset($groups[$section]);
                $groups[$section] = $group;
            }
        }

        return $groups;
    }

    /** @param list<Field> $fields */
    public static function expanded(string $section, array $fields): bool
    {
        // Required fields are never tucked away. Optional groups still use native details.
        return collect($fields)->contains(fn (Field $field) => $field->required)
            || ! in_array($section, ['Weitere Optionen', 'Bildzuschnitt', 'Suchbegriffe', 'Suchmaschinen', 'Bilder & Medien', 'Inhaltsbausteine', 'Gebühren & Online-Dienst', 'Zuständigkeiten & Zuordnungen']);
    }
}
