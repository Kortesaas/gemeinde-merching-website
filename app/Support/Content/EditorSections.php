<?php

namespace App\Support\Content;

use App\Admin\Fields\Field;

final class EditorSections
{
    /**
     * @param  list<Field>  $fields
     * @return array<string,list<Field>>
     */
    public static function group(array $fields): array
    {
        $groups = [];
        foreach ($fields as $field) {
            $name = $field->name;
            $section = match (true) {
                str_starts_with($name, 'seo_') || $name === 'meta_description' => 'SEO',
                in_array($name, ['contacts', 'people', 'departments', 'department_id', 'location_id', 'organization_id', 'contact_person_id', 'relatedServices', 'lifeSituations', 'memberships', 'committeeMemberships', 'council_term_id']) => 'Beziehungen',
                in_array($name, ['media', 'items', 'focal_x', 'focal_y', 'alt_text', 'is_decorative', 'copyright', 'creator', 'caption', 'language']) => 'Medien',
                $name === 'blocks' => 'Inhaltsbausteine',
                default => 'Inhalt',
            };
            $groups[$section][] = $field;
        }

        // Stable editorial order regardless of field declaration order.
        $order = ['Inhalt', 'Medien', 'Inhaltsbausteine', 'Beziehungen', 'SEO'];
        uksort($groups, fn ($a, $b) => array_search($a, $order, true) <=> array_search($b, $order, true));

        return $groups;
    }
}
