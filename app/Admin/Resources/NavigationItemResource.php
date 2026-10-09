<?php

namespace App\Admin\Resources;

use App\Admin\ContentResource;
use App\Admin\Fields;
use App\Admin\Options;
use App\Enums\NavigationMenu;
use App\Exceptions\DomainRuleViolation;
use App\Models\NavigationItem;
use App\Services\Navigation\NavigationManager;
use App\Support\Authorization\ContentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Validator;

/**
 * Menus are independent of URLs: an entry points to a record's canonical
 * route (path changes do not break it) or an external URL.
 *
 * @extends ContentResource<NavigationItem>
 */
class NavigationItemResource extends ContentResource
{
    public function model(): string
    {
        return NavigationItem::class;
    }

    public function type(): ContentType
    {
        return ContentType::Navigation;
    }

    public function slug(): string
    {
        return 'navigation';
    }

    public function label(): string
    {
        return 'Navigationseintrag';
    }

    public function pluralLabel(): string
    {
        return 'Navigation';
    }

    public function searchColumns(): array
    {
        return ['label'];
    }

    public function fields(?Model $model): array
    {
        return [
            Fields\Select::make('menu', 'Menü')->enum(NavigationMenu::class)->required(),
            Fields\Text::make('label', 'Beschriftung')->required(),
            Fields\BelongsTo::make('parent_id', 'Übergeordneter Eintrag')->options(fn (?Model $m) => Options::navigationItems($m)),
            Fields\BelongsTo::make('public_route_id', 'Ziel: Inhalt')->options(fn () => Options::canonicalRoutes()),
            Fields\BelongsTo::make('external_resource_id', 'Ziel: verwalteter externer Link')->options(fn () => Options::externalResources()),
            Fields\Url::make('url', 'Ziel: externe Adresse'),
            Fields\Checkbox::make('is_active', 'Aktiv'),
            Fields\Number::make('sort_order', 'Reihenfolge'),
        ];
    }

    public function after(Validator $validator, ?Model $model): void
    {
        if ($validator->errors()->isNotEmpty()) {
            return;
        }
        $preview = $model ? clone $model : new NavigationItem;
        foreach ($this->fields($model) as $field) {
            $field->fill($preview, $validator->getData());
        }
        try {
            app(NavigationManager::class)->validate($preview);
        } catch (DomainRuleViolation $e) {
            $validator->errors()->add($e->field, $e->getMessage());
        }
    }

    public function columns(Model $model): array
    {
        return ['Menü' => $model->menu->label(), 'Ziel' => (string) $model->href()];
    }
}
