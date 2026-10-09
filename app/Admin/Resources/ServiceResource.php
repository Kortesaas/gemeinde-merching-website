<?php

namespace App\Admin\Resources;

use App\Admin\ContentResource;
use App\Admin\Fields;
use App\Admin\Options;
use App\Enums\CategoryContext;
use App\Enums\OnlineServiceMode;
use App\Models\Service;
use App\Models\Service as ServiceModel;
use App\Rules\ControlledText;
use App\Support\Authorization\ContentType;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends ContentResource<Service>
 */
class ServiceResource extends ContentResource
{
    public function model(): string
    {
        return Service::class;
    }

    public function type(): ContentType
    {
        return ContentType::Service;
    }

    public function slug(): string
    {
        return 'buergerservice';
    }

    public function label(): string
    {
        return 'Leistung';
    }

    public function pluralLabel(): string
    {
        return 'Bürgerservice-Leistungen';
    }

    public function fields(?Model $model): array
    {
        return [
            Fields\Text::make('title', 'Titel')->required(),
            Fields\Text::make('sort_title', 'Sortiertitel (A–Z)')->hint('Optional, z. B. „Personalausweis“ für „Antrag auf Personalausweis“.'),
            Fields\Lines::make('aliases', 'Suchbegriffe / Synonyme')->hint('Ein Begriff pro Zeile, z. B. „Perso“, „Ausweis“.')
                ->eachLine(['string', 'max:255'], 50)
                ->using(
                    fn (Model $m) => $m instanceof ServiceModel && $m->exists ? array_values(array_map('strval', $m->aliases()->pluck('alias')->all())) : [],
                    function (Model $m, array $aliases) {
                        /** @var ServiceModel $m */
                        $m->aliases()->whereNotIn('alias', $aliases)->delete();
                        foreach ($aliases as $alias) {
                            $m->aliases()->firstOrCreate(['alias' => $alias]);
                        }
                    },
                ),
            Fields\Textarea::make('summary', 'Kurzbeschreibung')->rules(['max:1000']),
            Fields\Markdown::make('body', 'Beschreibung / Ablauf'),
            Fields\Textarea::make('prerequisites', 'Voraussetzungen')->rules(['max:10000', new ControlledText]),
            Fields\Textarea::make('required_items', 'Benötigte Unterlagen / Gegenstände')->rules(['max:10000', new ControlledText]),
            Fields\Textarea::make('processing_duration', 'Bearbeitungsdauer')->rules(['max:2000', new ControlledText]),
            Fields\Textarea::make('important_notice', 'Wichtiger Hinweis')->rules(['max:5000', new ControlledText]),
            Fields\Select::make('online_service_mode', 'Art des Online-Dienstes')->enum(OnlineServiceMode::class),
            Fields\Rows::make('fees', 'Gebühren')->definition('fees'),
            Fields\BelongsTo::make('category_id', 'Kategorie')->options(fn () => Options::categories(CategoryContext::Service)),
            Fields\BelongsTo::make('online_service_resource_id', 'Online-Dienst')->options(fn () => Options::externalResources()),
            Fields\BelongsToMany::make('departments', 'Zuständige Stellen')->options(fn () => Options::departments())->sortable(),
            Fields\BelongsToMany::make('contacts', 'Ansprechpersonen')->options(fn () => Options::people())->sortable(),
            Fields\BelongsToMany::make('relatedServices', 'Verwandte Leistungen')->options(fn (?Model $m) => Options::services($m))->sortable(),
            Fields\Number::make('sort_order', 'Reihenfolge'),
        ];
    }
}
