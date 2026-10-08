<?php

namespace App\Admin\Resources;

use App\Admin\ContentResource;
use App\Admin\Fields;
use App\Admin\Options;
use App\Models\Person;
use App\Support\Authorization\ContentType;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends ContentResource<Person>
 */
class PersonResource extends ContentResource
{
    public function model(): string
    {
        return Person::class;
    }

    public function type(): ContentType
    {
        return ContentType::Person;
    }

    public function slug(): string
    {
        return 'personen';
    }

    public function label(): string
    {
        return 'Person';
    }

    public function pluralLabel(): string
    {
        return 'Personen';
    }

    public function searchColumns(): array
    {
        return ['last_name', 'first_name', 'display_name', 'job_title', 'responsibilities'];
    }

    public function fields(?Model $model): array
    {
        return [
            Fields\Text::make('salutation', 'Anrede')->max(30),
            Fields\Text::make('academic_title', 'Titel')->max(50),
            Fields\Text::make('first_name', 'Vorname')->max(120),
            Fields\Text::make('last_name', 'Nachname')->max(120)->required(),
            Fields\Text::make('display_name', 'Anzeigename')->hint('Optional; sonst aus Titel, Vor- und Nachname.'),
            Fields\Text::make('job_title', 'Funktion / Stellenbezeichnung'),
            Fields\BelongsToMany::make('departments', 'Ämter / Sachgebiete')->options(fn () => Options::departments())->sortable(),
            Fields\Textarea::make('responsibilities', 'Zuständigkeiten')->hint('Wird später für die Suche genutzt.'),
            Fields\Phone::make('phone', 'Telefon'),
            Fields\Phone::make('fax', 'Fax'),
            Fields\Email::make('email', 'E-Mail (öffentlich)'),
            Fields\Text::make('room', 'Zimmer')->max(120),
            Fields\Textarea::make('availability', 'Erreichbarkeit / Sprechzeiten'),
            Fields\Textarea::make('public_notes', 'Öffentliche Hinweise'),
            Fields\Checkbox::make('is_active', 'Aktiv')->hint('Ehemalige Beschäftigte deaktivieren statt löschen.'),
            Fields\Number::make('sort_order', 'Reihenfolge'),
        ];
    }

    public function columns(Model $model): array
    {
        return ['Funktion' => (string) $model->job_title];
    }
}
