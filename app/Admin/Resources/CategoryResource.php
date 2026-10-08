<?php

namespace App\Admin\Resources;

use App\Admin\ContentResource;
use App\Admin\Fields;
use App\Enums\CategoryContext;
use App\Models\Category;
use App\Support\Authorization\ContentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

/**
 * @extends ContentResource<Category>
 */
class CategoryResource extends ContentResource
{
    public function model(): string
    {
        return Category::class;
    }

    public function type(): ContentType
    {
        return ContentType::Taxonomy;
    }

    public function slug(): string
    {
        return 'kategorien';
    }

    public function label(): string
    {
        return 'Kategorie';
    }

    public function pluralLabel(): string
    {
        return 'Kategorien';
    }

    public function searchColumns(): array
    {
        return ['name'];
    }

    public function fields(?Model $model): array
    {
        return [
            Fields\Select::make('context', 'Bereich')->enum(CategoryContext::class)->required(),
            Fields\Text::make('name', 'Name')->max(120)->required(),
            Fields\Text::make('slug', 'Kürzel')->max(120)->rules(['regex:/^[a-z0-9]+(-[a-z0-9]+)*$/'])->hint('Optional; wird aus dem Namen erzeugt.'),
            Fields\Textarea::make('description', 'Beschreibung'),
            Fields\Number::make('sort_order', 'Reihenfolge'),
        ];
    }

    public function after(Validator $validator, ?Model $model): void
    {
        $data = $validator->getData();
        $slug = ($data['slug'] ?? '') !== '' ? $data['slug'] : Str::slug((string) ($data['name'] ?? ''), '-', 'de');
        $exists = Category::query()->where('context', $data['context'] ?? '')->where('slug', $slug)
            ->when($model?->exists, fn ($q) => $q->whereKeyNot($model?->getKey()))->exists();
        if ($exists) {
            $validator->errors()->add('name', 'In diesem Bereich gibt es bereits eine Kategorie mit diesem Namen/Kürzel.');
        }
    }

    protected function beforeSave(Model $model, array $data, Request $request): void
    {
        /** @var Category $model */
        if (($model->slug ?? '') === '') {
            $model->slug = Str::slug($model->name, '-', 'de');
        }
    }

    public function columns(Model $model): array
    {
        return ['Bereich' => $model->context->label()];
    }
}
