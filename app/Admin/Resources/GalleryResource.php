<?php

namespace App\Admin\Resources;

use App\Admin\ContentResource;
use App\Admin\Fields;
use App\Models\Gallery;
use App\Rules\ControlledText;
use App\Support\Authorization\ContentType;
use Illuminate\Database\Eloquent\Model;

/** @extends ContentResource<Gallery> */
class GalleryResource extends ContentResource
{
    public function model(): string
    {
        return Gallery::class;
    }

    public function type(): ContentType
    {
        return ContentType::Gallery;
    }

    public function slug(): string
    {
        return 'galerien';
    }

    public function label(): string
    {
        return 'Galerie';
    }

    public function pluralLabel(): string
    {
        return 'Galerien';
    }

    public function fields(?Model $model): array
    {
        return [Fields\Text::make('title', 'Titel')->required(), Fields\Textarea::make('description', 'Beschreibung')->rules(['max:10000', new ControlledText]),
            Fields\Rows::make('items', 'Bilder')->definition('items'), ];
    }
}
