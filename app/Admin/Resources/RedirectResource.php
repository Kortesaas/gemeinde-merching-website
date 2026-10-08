<?php

namespace App\Admin\Resources;

use App\Admin\ContentResource;
use App\Admin\Fields;
use App\Exceptions\DomainRuleViolation;
use App\Models\Redirect;
use App\Models\User;
use App\Services\Routing\RedirectManager;
use App\Support\Authorization\ContentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Saved through RedirectManager (duplicates, loops, chains, collisions).
 *
 * @extends ContentResource<Redirect>
 */
class RedirectResource extends ContentResource
{
    public function model(): string
    {
        return Redirect::class;
    }

    public function type(): ContentType
    {
        return ContentType::Redirect;
    }

    public function slug(): string
    {
        return 'weiterleitungen';
    }

    public function label(): string
    {
        return 'Weiterleitung';
    }

    public function pluralLabel(): string
    {
        return 'Weiterleitungen';
    }

    public function searchColumns(): array
    {
        return ['source_path', 'destination'];
    }

    public function fields(?Model $model): array
    {
        return [
            Fields\Text::make('source_path', 'Alte Adresse (Pfad)')->required()->hint('z. B. /veranstaltungskalender/ – genau wie bisher, inkl. Schrägstrich am Ende.'),
            Fields\Text::make('destination', 'Ziel')->max(2048)->hint('Pfad (/aktuelles) oder vollständige https://-Adresse. Leer bei „410 – entfernt“.'),
            Fields\Select::make('status_code', 'Art')->options(Redirect::STATUS_CODES)->required()->hint('Für dauerhaft umgezogene Inhalte immer 301.'),
            Fields\Checkbox::make('is_active', 'Aktiv'),
            Fields\Textarea::make('notes', 'Notizen (intern)'),
        ];
    }

    public function save(?Model $model, array $data, Request $request, User $editor): Model
    {
        try {
            return app(RedirectManager::class)->save([
                'source_path' => (string) $data['source_path'],
                'destination' => $data['destination'] ?? null,
                'status_code' => (int) $data['status_code'],
                'is_active' => (bool) ($data['is_active'] ?? false),
                'notes' => $data['notes'] ?? null,
            ], $model);
        } catch (DomainRuleViolation $e) {
            throw $e->toValidationException();
        }
    }

    public function delete(Model $model, User $editor): void
    {
        /** @var Redirect $model */
        app(RedirectManager::class)->delete($model);
    }

    public function columns(Model $model): array
    {
        return ['Ziel' => (string) ($model->destination ?? '–'), 'Art' => (string) $model->status_code, 'Aktiv' => $model->is_active ? 'ja' : 'nein'];
    }
}
