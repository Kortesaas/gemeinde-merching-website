<?php

namespace App\Admin\Resources;

use App\Admin\ContentResource;
use App\Admin\Fields;
use App\Admin\Options;
use App\Models\Department;
use App\Support\Authorization\ContentType;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends ContentResource<Department>
 */
class DepartmentResource extends ContentResource
{
    public function model(): string
    {
        return Department::class;
    }

    public function type(): ContentType
    {
        return ContentType::Department;
    }

    public function slug(): string
    {
        return 'aemter';
    }

    public function label(): string
    {
        return 'Amt / Sachgebiet';
    }

    public function pluralLabel(): string
    {
        return 'Ämter & Sachgebiete';
    }

    public function searchColumns(): array
    {
        return ['name', 'short_name'];
    }

    public function fields(?Model $model): array
    {
        return [
            Fields\Text::make('name', 'Name')->required(),
            Fields\Text::make('short_name', 'Kurzname')->max(50),
            Fields\Textarea::make('description', 'Beschreibung / Aufgaben'),
            Fields\Phone::make('phone', 'Telefon (allgemein)'),
            Fields\Email::make('email', 'E-Mail (allgemein, öffentlich)'),
            Fields\BelongsTo::make('location_id', 'Ort')->options(fn () => Options::locations()),
            Fields\Textarea::make('opening_hours', 'Öffnungszeiten'),
            Fields\Checkbox::make('is_active', 'Aktiv'),
            Fields\Number::make('sort_order', 'Reihenfolge'),
        ];
    }
}
