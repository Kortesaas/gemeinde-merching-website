<?php

namespace App\Admin\Resources;

use App\Admin\ContentResource;
use App\Admin\Fields;
use App\Models\CouncilMember;
use App\Rules\ControlledText;
use App\Support\Authorization\ContentType;
use Illuminate\Database\Eloquent\Model;

/** @extends ContentResource<CouncilMember> */
class CouncilMemberResource extends ContentResource
{
    public function model(): string
    {
        return CouncilMember::class;
    }

    public function type(): ContentType
    {
        return ContentType::CouncilMember;
    }

    public function slug(): string
    {
        return 'ratsmitglieder';
    }

    public function label(): string
    {
        return 'Ratsmitglied';
    }

    public function pluralLabel(): string
    {
        return 'Ratsmitglieder';
    }

    public function fields(?Model $model): array
    {
        return [Fields\Text::make('title', 'Name')->required(), Fields\Textarea::make('description', 'Beschreibung')->rules(['max:10000', new ControlledText])];
    }
}
