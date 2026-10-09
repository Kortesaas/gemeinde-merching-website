<?php

namespace App\Admin\Resources;

use App\Admin\ContentResource;
use App\Admin\Fields;
use App\Models\Committee;
use App\Models\CouncilTerm;
use App\Rules\ControlledText;
use App\Support\Authorization\ContentType;
use Illuminate\Database\Eloquent\Model;

/** @extends ContentResource<Committee> */
class CommitteeResource extends ContentResource
{
    public function model(): string
    {
        return Committee::class;
    }

    public function type(): ContentType
    {
        return ContentType::Committee;
    }

    public function slug(): string
    {
        return 'ausschuesse';
    }

    public function label(): string
    {
        return 'Ausschuss';
    }

    public function pluralLabel(): string
    {
        return 'Ausschüsse';
    }

    public function fields(?Model $model): array
    {
        return [Fields\Text::make('title', 'Titel')->required(), Fields\Textarea::make('description', 'Beschreibung')->rules(['max:10000', new ControlledText]),
            Fields\BelongsTo::make('council_term_id', 'Wahlperiode')->required()->options(fn () => CouncilTerm::query()->pluck('title', 'id')->all()), Fields\Number::make('sort_order', 'Reihenfolge'), Fields\Rows::make('committeeMemberships', 'Ausschussmitglieder')->definition('committeeMemberships'), ];
    }
}
