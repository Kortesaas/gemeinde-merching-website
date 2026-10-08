<?php

namespace App\Admin\Resources;

use App\Admin\ContentResource;
use App\Admin\Fields;
use App\Admin\Options;
use App\Enums\CategoryContext;
use App\Models\PublicNotice;
use App\Support\Authorization\ContentType;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends ContentResource<PublicNotice>
 */
class PublicNoticeResource extends ContentResource
{
    public function model(): string
    {
        return PublicNotice::class;
    }

    public function type(): ContentType
    {
        return ContentType::PublicNotice;
    }

    public function slug(): string
    {
        return 'bekanntmachungen';
    }

    public function label(): string
    {
        return 'Bekanntmachung';
    }

    public function pluralLabel(): string
    {
        return 'Bekanntmachungen';
    }

    public function fields(?Model $model): array
    {
        return [
            Fields\Text::make('title', 'Titel')->required(),
            Fields\Textarea::make('summary', 'Kurzbeschreibung')->rules(['max:1000']),
            Fields\Markdown::make('body', 'Text'),
            Fields\BelongsTo::make('category_id', 'Art / Kategorie')->options(fn () => Options::categories(CategoryContext::PublicNotice)),
            Fields\Date::make('published_on', 'Datum der Bekanntmachung')
                ->hint('Amtliches Datum. Der Anzeigezeitraum wird unter „Veröffentlichung“ festgelegt; danach bleibt die Bekanntmachung im Archiv.'),
            Fields\BelongsToMany::make('pages', 'Zugehörige Seiten')->options(fn () => Options::pages()),
            Fields\BelongsToMany::make('services', 'Zugehörige Leistungen')->options(fn () => Options::services()),
        ];
    }
}
