<?php

namespace App\Admin\Resources;

use App\Admin\ContentResource;
use App\Admin\Fields;
use App\Admin\Options;
use App\Enums\CategoryContext;
use App\Enums\EventOperationalStatus;
use App\Models\Event;
use App\Rules\ControlledText;
use App\Rules\RecurrenceRule;
use App\Support\Authorization\ContentType;
use App\Support\SiteTime;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Validator;

/**
 * @extends ContentResource<Event>
 */
class EventResource extends ContentResource
{
    public function model(): string
    {
        return Event::class;
    }

    public function type(): ContentType
    {
        return ContentType::Event;
    }

    public function slug(): string
    {
        return 'veranstaltungen';
    }

    public function label(): string
    {
        return 'Veranstaltung';
    }

    public function pluralLabel(): string
    {
        return 'Veranstaltungen';
    }

    public function fields(?Model $model): array
    {
        return [
            Fields\Text::make('title', 'Titel')->required(),
            Fields\Select::make('operational_status', 'Veranstaltungsstatus')->enum(EventOperationalStatus::class),
            Fields\Textarea::make('schedule_notice', 'Hinweis zur Terminänderung')->rules(['max:2000', new ControlledText]),
            Fields\DateTime::make('starts_at', 'Beginn')->required(),
            Fields\DateTime::make('ends_at', 'Ende'),
            Fields\Checkbox::make('all_day', 'Ganztägig')->hint('Uhrzeiten werden ignoriert; die Veranstaltung gilt von Tagesbeginn bis Tagesende.'),
            Fields\Markdown::make('description', 'Beschreibung'),
            Fields\BelongsTo::make('location_id', 'Ort (aus Verzeichnis)')->options(fn () => Options::locations()),
            Fields\Text::make('venue', 'Ort (Freitext)')->hint('Nur wenn der Ort nicht im Verzeichnis steht.'),
            Fields\BelongsTo::make('organization_id', 'Veranstalter (aus Verzeichnis)')->options(fn () => Options::organizations()),
            Fields\Text::make('organizer_name', 'Veranstalter (Freitext)'),
            Fields\BelongsTo::make('contact_person_id', 'Ansprechperson')->options(fn () => Options::people()),
            Fields\BelongsTo::make('category_id', 'Kategorie')->options(fn () => Options::categories(CategoryContext::Event)),
            Fields\Textarea::make('remarks', 'Hinweise'),
            Fields\Url::make('url', 'Weitere Informationen (Link)'),
            Fields\Url::make('registration_url', 'Anmeldung (Link)'),
            Fields\Text::make('recurrence_rule', 'Wiederholung (RRULE)')->max(500)->rules([new RecurrenceRule])
                ->hint('Optional, Format nach RFC 5545, z. B. FREQ=WEEKLY;BYDAY=TU. Wiederholungen werden später ausgewertet.'),
            Fields\Checkbox::make('auto_archive', 'Nach Ende automatisch aus aktuellen Listen entfernen'),
        ];
    }

    public function after(Validator $validator, ?Model $model): void
    {
        $data = $validator->getData();
        if ($validator->errors()->hasAny(['starts_at', 'ends_at'])) {
            return;
        }

        $start = SiteTime::fromInput($data['starts_at'] ?? null);
        $end = SiteTime::fromInput($data['ends_at'] ?? null);
        if ($start !== null && $end !== null && $end->lessThan($start)) {
            $validator->errors()->add('ends_at', 'Das Ende darf nicht vor dem Beginn liegen.');
        }
    }

    protected function beforeSave(Model $model, array $data, Request $request): void
    {
        /** @var Event $model */
        if ($model->operational_status === null) {
            $model->operational_status = EventOperationalStatus::Scheduled;
        }
        if ($model->all_day) {
            $start = SiteTime::fromUtc($model->starts_at)->startOfDay();
            $end = SiteTime::fromUtc($model->ends_at ?? $model->starts_at)->endOfDay();
            $model->starts_at = $start->utc();
            $model->ends_at = $end->utc()->startOfSecond();
        }
    }

    public function columns(Model $model): array
    {
        return ['Beginn' => SiteTime::format($model->starts_at)];
    }
}
