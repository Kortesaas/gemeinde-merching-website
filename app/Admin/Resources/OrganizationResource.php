<?php

namespace App\Admin\Resources;

use App\Admin\ContentResource;
use App\Admin\Fields;
use App\Admin\Options;
use App\Enums\CategoryContext;
use App\Enums\OrganizationType;
use App\Models\Organization;
use App\Rules\SafeUrl;
use App\Support\Authorization\ContentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Validator;

/**
 * @extends ContentResource<Organization>
 */
class OrganizationResource extends ContentResource
{
    public function model(): string
    {
        return Organization::class;
    }

    public function type(): ContentType
    {
        return ContentType::Organization;
    }

    public function slug(): string
    {
        return 'verzeichnis';
    }

    public function label(): string
    {
        return 'Eintrag';
    }

    public function pluralLabel(): string
    {
        return 'Vereine, Gewerbe & Gastronomie';
    }

    public function searchColumns(): array
    {
        return ['name', 'description'];
    }

    public function fields(?Model $model): array
    {
        return [
            Fields\Text::make('name', 'Name')->required(),
            Fields\Select::make('type', 'Art')->enum(OrganizationType::class)->required(),
            Fields\BelongsTo::make('category_id', 'Kategorie')->options(fn () => Options::categories(CategoryContext::Organization)),
            Fields\Textarea::make('description', 'Beschreibung'),
            Fields\Text::make('contact_name', 'Ansprechperson'),
            Fields\Text::make('street', 'Straße und Hausnummer'),
            Fields\Text::make('postal_code', 'PLZ')->max(10)->rules(['regex:/^[0-9]{4,5}$/']),
            Fields\Text::make('city', 'Ort')->max(120),
            Fields\Phone::make('phone', 'Telefon'),
            Fields\Email::make('email', 'E-Mail'),
            Fields\Url::make('website', 'Website'),
            Fields\Lines::make('links', 'Weitere Links')->hint('Ein Link pro Zeile im Format „Bezeichnung | https://…“.')
                ->eachLine(['string', 'max:2300'], 10)
                ->using(
                    fn (Model $m) => $m instanceof Organization && $m->exists ? array_values($m->links()->get()->map(fn ($l) => $l->label.' | '.$l->url)->all()) : [],
                    function (Model $m, array $lines) {
                        /** @var Organization $m */
                        $m->links()->delete();
                        foreach ($lines as $i => $line) {
                            [$label, $url] = array_map('trim', explode('|', $line, 2));
                            $m->links()->create(['label' => $label, 'url' => $url, 'sort_order' => $i * 10]);
                        }
                    },
                ),
            Fields\Checkbox::make('is_active', 'Aktiv'),
            Fields\Number::make('sort_order', 'Reihenfolge'),
        ];
    }

    public function after(Validator $validator, ?Model $model): void
    {
        foreach (Fields\Lines::split($validator->getData()['links'] ?? '') as $line) {
            $parts = array_map('trim', explode('|', $line, 2));
            if (count($parts) !== 2 || $parts[0] === '' || ! SafeUrl::isSafe($parts[1])) {
                $validator->errors()->add('links', "Ungültiger Link „{$line}“. Format: Bezeichnung | https://…");
                break;
            }
        }
    }

    public function columns(Model $model): array
    {
        return ['Art' => $model->type->label()];
    }
}
