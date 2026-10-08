<?php

namespace App\Support\Authorization;

/**
 * Actions on a content type. Combined with ContentType into permission names
 * such as "article.publish" (see ContentType::permission()).
 *
 * Editing and publishing are deliberately separate: "edit" allows saving
 * drafts; making content public, changing a live publication window or
 * withdrawing it requires "publish". Permanent deletion is the separate,
 * stronger "force-delete".
 */
enum Ability: string
{
    case View = 'view';
    case Create = 'create';
    case Edit = 'edit';
    case Publish = 'publish';
    case Archive = 'archive';
    case Delete = 'delete';
    case ForceDelete = 'force-delete';

    public function label(): string
    {
        return match ($this) {
            self::View => 'ansehen',
            self::Create => 'anlegen',
            self::Edit => 'bearbeiten',
            self::Publish => 'veröffentlichen',
            self::Archive => 'archivieren',
            self::Delete => 'in den Papierkorb legen / wiederherstellen',
            self::ForceDelete => 'endgültig löschen',
        };
    }
}
