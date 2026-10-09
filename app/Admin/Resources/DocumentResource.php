<?php

namespace App\Admin\Resources;

use App\Admin\ContentResource;
use App\Admin\Fields;
use App\Admin\Options;
use App\Enums\AccessibilityStatus;
use App\Enums\CategoryContext;
use App\Exceptions\DomainRuleViolation;
use App\Models\Document;
use App\Services\Content\ContentUsage;
use App\Services\Content\DocumentStorage;
use App\Support\Authorization\ContentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Validator;

/**
 * @extends ContentResource<Document>
 */
class DocumentResource extends ContentResource
{
    public function model(): string
    {
        return Document::class;
    }

    public function type(): ContentType
    {
        return ContentType::Document;
    }

    public function slug(): string
    {
        return 'dokumente';
    }

    public function label(): string
    {
        return 'Dokument';
    }

    public function pluralLabel(): string
    {
        return 'Dokumente';
    }

    public function fields(?Model $model): array
    {
        return [
            Fields\Text::make('title', 'Titel')->required(),
            Fields\Textarea::make('description', 'Beschreibung'),
            Fields\BelongsTo::make('category_id', 'Kategorie')->options(fn () => Options::categories(CategoryContext::Document)),
            Fields\Number::make('year', 'Jahr')->between(1800, 2200),
            Fields\Date::make('document_date', 'Datum des Dokuments'),
            Fields\Date::make('valid_from', 'Gültig ab'),
            Fields\Date::make('valid_until', 'Gültig bis')->rules(['after_or_equal:valid_from']),
            Fields\Text::make('language', 'Sprache (Code)')->max(10)->rules(['regex:/^[a-z]{2,3}(-[A-Za-z0-9]{2,8})*$/'])->hint('z. B. de, en, de-x-ls für Leichte Sprache'),
            Fields\Select::make('accessibility_status', 'Barrierefreiheit')->enum(AccessibilityStatus::class)->required()
                ->hint('Neue Dateien sind „Nicht geprüft“, bis jemand die Barrierefreiheit geprüft hat.'),
            Fields\Textarea::make('accessibility_notes', 'Hinweise zur Barrierefreiheit'),
            Fields\BelongsTo::make('accessible_alternative_id', 'Barrierefreie Alternative')->options(fn (?Model $m) => Options::documents($m)),
            Fields\BelongsTo::make('replaces_document_id', 'Ersetzt Dokument')->options(fn (?Model $m) => Options::documents($m))
                ->hint('Ältere Fassung, die durch dieses Dokument ersetzt wird.'),
        ];
    }

    public function rules(?Model $model): array
    {
        $file = ['file', 'max:'.(int) config('uploads.max_kilobytes')];

        return ['file' => $model?->exists ? ['nullable', ...$file] : ['required', ...$file]];
    }

    public function after(Validator $validator, ?Model $model): void
    {
        $data = $validator->getData();

        if (($data['accessibility_status'] ?? null) === AccessibilityStatus::AlternativeProvided->value && empty($data['accessible_alternative_id'])) {
            $validator->errors()->add('accessible_alternative_id', 'Bitte wählen Sie die barrierefreie Alternative aus.');
        }

        // No supersession cycles (A replaces B replaces … A).
        $replaces = isset($data['replaces_document_id']) && $data['replaces_document_id'] !== '' ? (int) $data['replaces_document_id'] : null;
        if ($replaces !== null && $model?->exists) {
            $other = $replaces === $model->getKey() ? null : Document::withTrashed()->find($replaces);
            if ($other === null) {
                $validator->errors()->add('replaces_document_id', 'Ein Dokument kann sich nicht selbst ersetzen.');
            }
            for ($hops = 0; $other !== null && $hops < 50; $hops++, $other = $other->replaces) {
                if ($other->replaces_document_id === $model->getKey()) {
                    $validator->errors()->add('replaces_document_id', 'Diese Zuordnung würde einen Kreis von Ersetzungen erzeugen.');
                    break;
                }
            }
        }
        if ($replaces !== null && Document::withTrashed()->where('replaces_document_id', $replaces)->when($model?->exists, fn ($q) => $q->whereKeyNot($model?->getKey()))->exists()) {
            $validator->errors()->add('replaces_document_id', 'Dieses Dokument wurde bereits durch ein anderes ersetzt.');
        }
    }

    protected function beforeSave(Model $model, array $data, Request $request): void
    {
        /** @var Document $model */
        if ($this->applyingProposal) {
            return; // proposals change metadata only; files are replaced by publishers
        }

        $file = $request->file('file');
        if ($file instanceof UploadedFile) {
            app(DocumentStorage::class)->attach($model, $file);
            $path = $model->file_path;
            $this->onFailure[] = function () use ($path): void {
                Storage::disk((string) config('uploads.disk'))->delete($path);
            };
        }
    }

    public function beforeForceDelete(Model $model): void
    {
        parent::beforeForceDelete($model);
        /** @var Document $model */
        $usages = app(ContentUsage::class)->of($model);
        if ($usages !== []) {
            throw new DomainRuleViolation('Das Dokument wird noch an '.count($usages).' Stelle(n) verwendet und kann nicht endgültig gelöscht werden.');
        }
    }

    public function afterForceDelete(Model $model): void
    {
        /** @var Document $model */
        app(DocumentStorage::class)->deleteFileAfterCommit($model);
    }

    public function columns(Model $model): array
    {
        /** @var Document $model */
        return [
            'Datei' => strtoupper($model->extension).', '.number_format($model->size_bytes / 1024, 0, ',', '.').' KB',
            'Barrierefreiheit' => $model->accessibility_status->label(),
        ];
    }
}
