<?php

namespace App\Admin\Resources;

use App\Admin\ContentResource;
use App\Admin\Fields;
use App\Admin\Options;
use App\Models\Page;
use App\Support\Authorization\ContentType;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends ContentResource<Page>
 */
class PageResource extends ContentResource
{
    public function model(): string
    {
        return Page::class;
    }

    public function type(): ContentType
    {
        return ContentType::Page;
    }

    public function slug(): string
    {
        return 'seiten';
    }

    public function label(): string
    {
        return 'Seite';
    }

    public function pluralLabel(): string
    {
        return 'Seiten';
    }

    public function fields(?Model $model): array
    {
        return [
            Fields\Text::make('title', 'Titel')->required(),
            Fields\Textarea::make('summary', 'Kurzbeschreibung')->rules(['max:1000']),
            Fields\Markdown::make('body', 'Inhalt'),
            Fields\BelongsTo::make('department_id', 'Zuständige Stelle')->options(fn () => Options::departments()),
            Fields\BelongsToMany::make('contacts', 'Ansprechpersonen')->options(fn () => Options::people())->sortable(),
        ];
    }
}
