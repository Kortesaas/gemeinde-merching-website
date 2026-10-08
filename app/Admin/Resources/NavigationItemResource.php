<?php

namespace App\Admin\Resources;

use App\Admin\ContentResource;
use App\Admin\Fields;
use App\Admin\Options;
use App\Enums\NavigationMenu;
use App\Models\NavigationItem;
use App\Rules\SafeUrl;
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
            Fields\Url::make('url', 'Ziel: externe Adresse'),
            Fields\Checkbox::make('is_active', 'Aktiv'),
            Fields\Number::make('sort_order', 'Reihenfolge'),
        ];
    }

    public function after(Validator $validator, ?Model $model): void
    {
        $data = $validator->getData();
        $hasRoute = ! empty($data['public_route_id']);
        $hasUrl = ! empty($data['url']);
        if ($hasRoute === $hasUrl) {
            $validator->errors()->add('public_route_id', 'Bitte genau ein Ziel wählen: einen Inhalt oder eine externe Adresse.');
        }
        if ($hasUrl && ! SafeUrl::isSafe((string) $data['url'])) {
            $validator->errors()->add('url', 'Ungültige Adresse.');
        }
        $parent = ! empty($data['parent_id']) ? NavigationItem::find((int) $data['parent_id']) : null;
        if ($parent !== null && $parent->menu->value !== ($data['menu'] ?? null)) {
            $validator->errors()->add('parent_id', 'Der übergeordnete Eintrag muss im selben Menü liegen.');
        }
        for ($hops = 0; $model?->exists && $parent !== null && $hops < 20; $hops++, $parent = $parent->parent) {
            if ($parent->is($model)) {
                $validator->errors()->add('parent_id', 'Ein Eintrag kann nicht unter sich selbst einsortiert werden.');
                break;
            }
        }
    }

    public function columns(Model $model): array
    {
        return ['Menü' => $model->menu->label(), 'Ziel' => (string) $model->href()];
    }
}
