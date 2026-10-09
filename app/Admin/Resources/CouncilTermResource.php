<?php

namespace App\Admin\Resources;

use App\Admin\ContentResource;
use App\Admin\Fields;
use App\Models\CouncilTerm;
use App\Rules\ControlledText;
use App\Support\Authorization\ContentType;
use Illuminate\Database\Eloquent\Model;

/** @extends ContentResource<CouncilTerm> */
class CouncilTermResource extends ContentResource
{
    public function model(): string
    {
        return CouncilTerm::class;
    }

    public function type(): ContentType
    {
        return ContentType::CouncilTerm;
    }

    public function slug(): string
    {
        return 'wahlperioden';
    }

    public function label(): string
    {
        return 'Wahlperiode';
    }

    public function pluralLabel(): string
    {
        return 'Wahlperioden';
    }

    public function fields(?Model $model): array
    {
        return [Fields\Text::make('title', 'Titel')->required(), Fields\Textarea::make('description', 'Beschreibung')->rules(['max:10000', new ControlledText]),
            Fields\Date::make('starts_on', 'Beginn der Wahlperiode'), Fields\Date::make('ends_on', 'Ende der Wahlperiode'), Fields\Checkbox::make('is_historical', 'Historische Wahlperiode'), Fields\Rows::make('memberships', 'Ratsmitglieder dieser Wahlperiode')->definition('memberships'), ];
    }
}
