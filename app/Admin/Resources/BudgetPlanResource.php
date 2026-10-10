<?php

namespace App\Admin\Resources;

use App\Admin\ContentResource;
use App\Admin\Fields;
use App\Enums\AccessibilityStatus;
use App\Exceptions\DomainRuleViolation;
use App\Models\BudgetPlan;
use App\Rules\ControlledText;
use App\Support\Authorization\ContentType;
use Illuminate\Database\Eloquent\Model;

/** @extends ContentResource<BudgetPlan> */
class BudgetPlanResource extends ContentResource
{
    public function model(): string
    {
        return BudgetPlan::class;
    }

    public function type(): ContentType
    {
        return ContentType::BudgetPlan;
    }

    public function slug(): string
    {
        return 'haushaltsplaene';
    }

    public function label(): string
    {
        return 'Haushaltsplan';
    }

    public function pluralLabel(): string
    {
        return 'Haushaltspläne';
    }

    public function fields(?Model $model): array
    {
        return [
            Fields\Number::make('year', 'Haushaltsjahr')->required()->between(1900, 2200),
            Fields\Text::make('topic', 'Thema / Art')->required()->rules(['max:180'])->hint('Zum Beispiel Gemeindehaushalt, Schulverband, Nachtrag oder Berichtigung. Mehrere Pakete pro Jahr sind möglich.'),
            Fields\Text::make('title', 'Titel')->required()->hint('Ein eindeutiger öffentlicher Titel, zum Beispiel „Nachtrag zum Gemeindehaushalt 2026“.'),
            Fields\Textarea::make('description', 'Kurze Beschreibung (optional)')->rules(['max:5000', new ControlledText]),
            Fields\BudgetComponents::make('components', 'PDF-Dateien in Reihenfolge')->definition('budgetComponents')->forPlan($model?->getKey()),
            Fields\Checkbox::make('show_components', 'Einzelne PDF-Dateien zusätzlich öffentlich anbieten'),
            Fields\Select::make('accessibility_status', 'Barrierefreiheit des Gesamt-PDF')->options(collect(AccessibilityStatus::cases())->reject(fn ($s) => $s === AccessibilityStatus::AlternativeProvided)->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all())->hint('Das neu erstellte Gesamt-PDF muss separat geprüft werden. Nach jeder neuen Zusammenstellung wird dieser Status zurückgesetzt.'),
            Fields\Textarea::make('accessibility_notes', 'Hinweis zur Barrierefreiheit (optional)')->rules(['max:5000', new ControlledText]),
        ];
    }

    public function columns(Model $model): array
    {
        return ['Jahr' => (string) $model->year, 'Thema / Art' => $model->topic, 'Gesamt-PDF' => ['ready' => 'Erstellt', 'stale' => 'Neu erstellen', 'failed' => 'Fehlgeschlagen'][$model->generation_status]];
    }

    public function beforeForceDelete(Model $model): void
    {
        parent::beforeForceDelete($model);
        if ($model->sources()->exists() || $model->publications()->exists()) {
            throw new DomainRuleViolation('Haushaltspläne mit Uploads oder Veröffentlichungsnachweisen bleiben für die Nachvollziehbarkeit im Papierkorb erhalten.');
        }
    }
}
