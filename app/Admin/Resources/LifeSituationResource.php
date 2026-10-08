<?php

namespace App\Admin\Resources;

use App\Admin\ContentResource;
use App\Admin\Fields;
use App\Admin\Options;
use App\Models\LifeSituation;
use App\Support\Authorization\ContentType;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends ContentResource<LifeSituation>
 */
class LifeSituationResource extends ContentResource
{
    public function model(): string
    {
        return LifeSituation::class;
    }

    public function type(): ContentType
    {
        return ContentType::LifeSituation;
    }

    public function slug(): string
    {
        return 'lebenslagen';
    }

    public function label(): string
    {
        return 'Lebenslage';
    }

    public function pluralLabel(): string
    {
        return 'Lebenslagen';
    }

    public function fields(?Model $model): array
    {
        return [
            Fields\Text::make('title', 'Titel')->required(),
            Fields\Textarea::make('summary', 'Kurzbeschreibung')->rules(['max:1000']),
            Fields\Markdown::make('body', 'Einleitung'),
            Fields\BelongsToMany::make('services', 'Leistungen')->options(fn () => Options::services())->sortable(),
            Fields\Number::make('sort_order', 'Reihenfolge'),
        ];
    }
}
