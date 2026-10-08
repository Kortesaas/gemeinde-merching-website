<?php

namespace App\Admin\Resources;

use App\Admin\ContentResource;
use App\Admin\Fields;
use App\Enums\AlertSeverity;
use App\Models\SiteAlert;
use App\Support\Authorization\ContentType;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends ContentResource<SiteAlert>
 */
class SiteAlertResource extends ContentResource
{
    public function model(): string
    {
        return SiteAlert::class;
    }

    public function type(): ContentType
    {
        return ContentType::SiteAlert;
    }

    public function slug(): string
    {
        return 'hinweise';
    }

    public function label(): string
    {
        return 'Hinweis-Banner';
    }

    public function pluralLabel(): string
    {
        return 'Hinweis-Banner';
    }

    public function fields(?Model $model): array
    {
        return [
            Fields\Text::make('title', 'Titel')->required(),
            Fields\Textarea::make('body', 'Text')->required()->rules(['max:2000']),
            Fields\Select::make('severity', 'Dringlichkeit')->enum(AlertSeverity::class)->required(),
            Fields\Url::make('link_url', 'Link (optional)'),
            Fields\Text::make('link_label', 'Linktext')->rules(['required_with:link_url'])->hint('Beschreibend, z. B. „Umleitungsplan ansehen“.'),
        ];
    }

    public function columns(Model $model): array
    {
        return ['Dringlichkeit' => $model->severity->label()];
    }
}
