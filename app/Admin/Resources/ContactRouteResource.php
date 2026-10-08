<?php

namespace App\Admin\Resources;

use App\Admin\ContentResource;
use App\Admin\Fields;
use App\Admin\Options;
use App\Models\ContactRoute;
use App\Support\Authorization\Ability;
use App\Support\Authorization\ContentType;
use Illuminate\Database\Eloquent\Model;

/**
 * Recipient addresses are only rendered in the edit form (contact-route.edit)
 * and never appear in lists, revisions or audit metadata.
 *
 * @extends ContentResource<ContactRoute>
 */
class ContactRouteResource extends ContentResource
{
    public function model(): string
    {
        return ContactRoute::class;
    }

    public function type(): ContentType
    {
        return ContentType::ContactRoute;
    }

    public function slug(): string
    {
        return 'kontakt-themen';
    }

    public function label(): string
    {
        return 'Kontaktformular-Thema';
    }

    public function pluralLabel(): string
    {
        return 'Kontaktformular-Themen';
    }

    public function searchColumns(): array
    {
        return ['label'];
    }

    public function fields(?Model $model): array
    {
        $fields = [
            Fields\Text::make('label', 'Thema (öffentliche Bezeichnung)')->required()->hint('z. B. „Meldewesen“ – Besucher wählen das Thema, nie eine Adresse.'),
            Fields\Textarea::make('explanation', 'Erläuterung (öffentlich)')->rules(['max:1000']),
        ];

        // Internal addresses only for users who may edit contact routes – a
        // read-only view never renders them.
        if (auth()->user()?->can(ContentType::ContactRoute->permission(Ability::Edit))) {
            $fields[] = Fields\Lines::make('recipients', 'Interne Empfängeradressen')->required()
                ->eachLine(['email:rfc', 'max:255'], 10)
                ->hint('Eine Adresse pro Zeile. Intern: verschlüsselt gespeichert und nie öffentlich angezeigt.');
        }

        return [
            ...$fields,
            Fields\BelongsTo::make('department_id', 'Zugehörige Stelle')->options(fn () => Options::departments()),
            Fields\Checkbox::make('is_active', 'Aktiv'),
            Fields\Number::make('sort_order', 'Reihenfolge'),
        ];
    }
}
