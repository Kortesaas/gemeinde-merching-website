<?php

namespace App\Admin\Resources;

use App\Admin\ContentResource;
use App\Admin\Fields;
use App\Models\ContactRoute;
use App\Models\Department;
use App\Models\Location;
use App\Models\SiteSettings;
use App\Support\Authorization\ContentType;
use Illuminate\Database\Eloquent\Model;

/** @extends ContentResource<SiteSettings> */
class SiteSettingsResource extends ContentResource
{
    public function model(): string
    {
        return SiteSettings::class;
    }

    public function type(): ContentType
    {
        return ContentType::SiteSettings;
    }

    public function slug(): string
    {
        return 'einstellungen';
    }

    public function label(): string
    {
        return 'Website-Einstellungen';
    }

    public function pluralLabel(): string
    {
        return 'Website-Einstellungen';
    }

    public function searchColumns(): array
    {
        return ['municipality_name'];
    }

    public function fields(?Model $model): array
    {
        return [
            Fields\Text::make('municipality_name', 'Name der Gemeinde')->required(),
            Fields\BelongsTo::make('town_hall_location_id', 'Rathaus (Adresse und Öffnungszeiten)')->options(fn () => Location::query()->pluck('name', 'id')->all()),
            Fields\BelongsTo::make('central_department_id', 'Zentrale Stelle (Telefon / E-Mail)')->options(fn () => Department::query()->pluck('name', 'id')->all()),
            Fields\BelongsTo::make('central_contact_route_id', 'Zentrales Kontaktformular-Thema')->options(fn () => ContactRoute::query()->where('is_active', true)->pluck('label', 'id')->all()),
            Fields\BelongsTo::make('works_department_id', 'Bauhof-Kontakt')->options(fn () => Department::query()->pluck('name', 'id')->all()),
            Fields\BelongsTo::make('recycling_location_id', 'Wertstoffsammelstelle')->options(fn () => Location::query()->pluck('name', 'id')->all()),
            Fields\Textarea::make('postal_address', 'Abweichende Postanschrift'), Fields\Textarea::make('legal_contact', 'Rechtlicher Kontakt / Footer'),
            Fields\Text::make('default_seo_title', 'Standard-Seitentitel'), Fields\Textarea::make('default_meta_description', 'Standard-Meta-Beschreibung')->rules(['max:500']),
        ];
    }
}
