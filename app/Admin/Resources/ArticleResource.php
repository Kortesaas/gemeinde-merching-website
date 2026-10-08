<?php

namespace App\Admin\Resources;

use App\Admin\ContentResource;
use App\Admin\Fields;
use App\Admin\Options;
use App\Enums\CategoryContext;
use App\Models\Article;
use App\Support\Authorization\ContentType;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends ContentResource<Article>
 */
class ArticleResource extends ContentResource
{
    public function model(): string
    {
        return Article::class;
    }

    public function type(): ContentType
    {
        return ContentType::Article;
    }

    public function slug(): string
    {
        return 'artikel';
    }

    public function label(): string
    {
        return 'Artikel';
    }

    public function pluralLabel(): string
    {
        return 'Artikel (Aktuelles)';
    }

    public function fields(?Model $model): array
    {
        return [
            Fields\Text::make('title', 'Titel')->required(),
            Fields\Textarea::make('summary', 'Kurztext (Teaser)')->hint('Wird in Übersichten angezeigt. Max. 1000 Zeichen.')->rules(['max:1000']),
            Fields\Markdown::make('body', 'Inhalt'),
            Fields\BelongsTo::make('category_id', 'Kategorie')->options(fn () => Options::categories(CategoryContext::Article)),
            Fields\BelongsToMany::make('tags', 'Schlagwörter')->options(fn () => Options::tags()),
            Fields\Text::make('author_name', 'Autor/in (Anzeigename)')->hint('Optional, z. B. „Gemeindeverwaltung“.'),
            Fields\BelongsTo::make('department_id', 'Zuständige Stelle')->options(fn () => Options::departments()),
            Fields\BelongsToMany::make('contacts', 'Ansprechpersonen')->options(fn () => Options::people())->sortable(),
            Fields\Checkbox::make('is_featured', 'Hervorgehoben'),
        ];
    }

    public function columns(Model $model): array
    {
        return ['Kategorie' => (string) $model->category?->name];
    }
}
