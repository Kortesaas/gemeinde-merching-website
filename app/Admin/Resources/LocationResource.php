<?php

namespace App\Admin\Resources;

use App\Admin\ContentResource;
use App\Admin\Fields;
use App\Admin\Options;
use App\Enums\LocationType;
use App\Models\Location;
use App\Rules\ControlledText;
use App\Support\Authorization\ContentType;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends ContentResource<Location>
 */
class LocationResource extends ContentResource
{
    public function model(): string
    {
        return Location::class;
    }

    public function type(): ContentType
    {
        return ContentType::Location;
    }

    public function slug(): string
    {
        return 'orte';
    }

    public function label(): string
    {
        return 'Ort / Einrichtung';
    }

    public function pluralLabel(): string
    {
        return 'Orte & Einrichtungen';
    }

    public function searchColumns(): array
    {
        return ['name', 'street'];
    }

    public function fields(?Model $model): array
    {
        return [
            Fields\Text::make('name', 'Name')->required(),
            Fields\Select::make('type', 'Art')->enum(LocationType::class)->required(),
            Fields\Textarea::make('description', 'Beschreibung'),
            Fields\Text::make('street', 'Straße und Hausnummer')->autocomplete('off'),
            Fields\Text::make('postal_code', 'PLZ')->max(10)->rules(['regex:/^[0-9]{4,5}$/']),
            Fields\Text::make('city', 'Ort')->max(120),
            Fields\Phone::make('phone', 'Telefon'),
            Fields\Email::make('email', 'E-Mail'),
            Fields\Textarea::make('opening_hours', 'Öffnungszeiten'),
            Fields\Textarea::make('accessibility_note', 'Zugänglichkeit / Barrierefreiheit')->rules(['max:5000', new ControlledText])->hint('Nur überprüfte Informationen eintragen; leer bedeutet unbekannt.'),
            Fields\Number::make('latitude', 'Breitengrad')->between(-90, 90, 'any'),
            Fields\Number::make('longitude', 'Längengrad')->between(-180, 180, 'any'),
            Fields\BelongsTo::make('map_resource_id', 'Kartenlink (externer Link)')->options(fn () => Options::externalResources())
                ->hint('Karten werden nur verlinkt, nicht eingebettet.'),
            Fields\Checkbox::make('is_active', 'Aktiv'),
            Fields\Number::make('sort_order', 'Reihenfolge'),
        ];
    }

    public function columns(Model $model): array
    {
        return ['Art' => $model->type->label()];
    }
}
