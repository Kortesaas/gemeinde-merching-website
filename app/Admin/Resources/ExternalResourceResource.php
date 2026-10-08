<?php

namespace App\Admin\Resources;

use App\Admin\ContentResource;
use App\Admin\Fields;
use App\Enums\ExternalResourceType;
use App\Exceptions\DomainRuleViolation;
use App\Models\ExternalResource;
use App\Services\Content\ContentUsage;
use App\Support\Authorization\ContentType;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends ContentResource<ExternalResource>
 */
class ExternalResourceResource extends ContentResource
{
    public function model(): string
    {
        return ExternalResource::class;
    }

    public function type(): ContentType
    {
        return ContentType::ExternalResource;
    }

    public function slug(): string
    {
        return 'links';
    }

    public function label(): string
    {
        return 'Link / Online-Dienst';
    }

    public function pluralLabel(): string
    {
        return 'Externe Links & Online-Dienste';
    }

    public function fields(?Model $model): array
    {
        return [
            Fields\Text::make('title', 'Bezeichnung (Linktext)')->required()->hint('Beschreibend formulieren, z. B. „Personalausweis online beantragen“ statt „hier“.'),
            Fields\Url::make('url', 'Adresse (URL)')->required(),
            Fields\Select::make('type', 'Art')->enum(ExternalResourceType::class)->required(),
            Fields\Text::make('provider_name', 'Anbieter')->hint('z. B. „Freistaat Bayern“'),
            Fields\Textarea::make('description', 'Beschreibung'),
            Fields\Textarea::make('privacy_note', 'Hinweis zu externem Dienst / Datenschutz'),
        ];
    }

    public function beforeForceDelete(Model $model): void
    {
        /** @var ExternalResource $model */
        if (app(ContentUsage::class)->isUsed($model)) {
            throw new DomainRuleViolation('Der Link wird noch verwendet und kann nicht endgültig gelöscht werden.');
        }
    }

    public function columns(Model $model): array
    {
        return ['Art' => $model->type->label()];
    }
}
