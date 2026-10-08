<?php

namespace App\Admin\Resources;

use App\Admin\ContentResource;
use App\Admin\Fields;
use App\Models\Tag;
use App\Support\Authorization\ContentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

/**
 * @extends ContentResource<Tag>
 */
class TagResource extends ContentResource
{
    public function model(): string
    {
        return Tag::class;
    }

    public function type(): ContentType
    {
        return ContentType::Taxonomy;
    }

    public function slug(): string
    {
        return 'schlagwoerter';
    }

    public function label(): string
    {
        return 'Schlagwort';
    }

    public function pluralLabel(): string
    {
        return 'Schlagwörter';
    }

    public function searchColumns(): array
    {
        return ['name'];
    }

    public function fields(?Model $model): array
    {
        return [Fields\Text::make('name', 'Name')->max(120)->required()];
    }

    public function after(Validator $validator, ?Model $model): void
    {
        $slug = Str::slug((string) ($validator->getData()['name'] ?? ''), '-', 'de');
        if (Tag::query()->where('slug', $slug)->when($model?->exists, fn ($q) => $q->whereKeyNot($model?->getKey()))->exists()) {
            $validator->errors()->add('name', 'Dieses Schlagwort gibt es bereits.');
        }
    }

    protected function beforeSave(Model $model, array $data, Request $request): void
    {
        /** @var Tag $model */
        $model->slug = Str::slug($model->name, '-', 'de');
    }
}
