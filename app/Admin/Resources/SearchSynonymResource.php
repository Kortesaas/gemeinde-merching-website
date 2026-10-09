<?php

namespace App\Admin\Resources;

use App\Admin\ContentResource;
use App\Admin\Fields;
use App\Models\SearchSynonym;
use App\Support\Authorization\ContentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/** @extends ContentResource<SearchSynonym> */
class SearchSynonymResource extends ContentResource
{
    public function model(): string
    {
        return SearchSynonym::class;
    }

    public function type(): ContentType
    {
        return ContentType::SearchSynonym;
    }

    public function slug(): string
    {
        return 'suchbegriffe';
    }

    public function label(): string
    {
        return 'Suchbegriff';
    }

    public function pluralLabel(): string
    {
        return 'Suchbegriffe';
    }

    public function searchColumns(): array
    {
        return ['phrase', 'alternatives'];
    }

    public function fields(?Model $model): array
    {
        return [
            Fields\Text::make('phrase', 'Suchbegriff')->required()->max(100)->rules([Rule::unique('search_synonyms', 'phrase')->ignore($model?->getKey())]),
            Fields\Text::make('alternatives', 'Alternative Begriffe')->required()->max(1000)->hint('Begriffe mit Semikolon trennen, z. B. Abfall; Wertstoff.'),
            Fields\Checkbox::make('is_active', 'Aktiv'),
        ];
    }
}
