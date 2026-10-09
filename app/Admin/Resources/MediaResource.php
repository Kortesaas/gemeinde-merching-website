<?php

namespace App\Admin\Resources;

use App\Admin\ContentResource;
use App\Admin\Fields;
use App\Exceptions\DomainRuleViolation;
use App\Models\Media;
use App\Services\Content\ContentUsage;
use App\Services\Content\MediaStorage;
use App\Support\Authorization\ContentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/** @extends ContentResource<Media> */
class MediaResource extends ContentResource
{
    public function model(): string
    {
        return Media::class;
    }

    public function type(): ContentType
    {
        return ContentType::Media;
    }

    public function slug(): string
    {
        return 'medien';
    }

    public function label(): string
    {
        return 'Medium';
    }

    public function pluralLabel(): string
    {
        return 'Medien';
    }

    public function fields(?Model $model): array
    {
        return [
            Fields\Text::make('title', 'Titel')->required(),
            Fields\Textarea::make('alt_text', 'Alternativtext')->hint('Für bedeutungstragende Bilder erforderlich. Ohne Text bleibt der Bildzweck ungeprüft.'),
            Fields\Checkbox::make('is_decorative', 'Bild ist rein dekorativ')->hint('Dekorative Bilder werden mit ausdrücklich leerem Alternativtext ausgegeben.'),
            Fields\Textarea::make('caption', 'Bildunterschrift'), Fields\Text::make('copyright', 'Urheberrecht / Quelle'),
            Fields\Text::make('creator', 'Fotograf / Urheber'), Fields\Text::make('language', 'Sprache')->max(10)->rules(['regex:/^[a-z]{2,3}(-[A-Za-z0-9]{2,8})*$/']),
        ];
    }

    public function rules(?Model $model): array
    {
        return ['file' => [$model?->exists ? 'nullable' : 'required', 'file', 'max:'.(int) config('uploads.max_kilobytes')]];
    }

    protected function beforeSave(Model $model, array $data, Request $request): void
    {
        if (empty($model->getAttribute('language'))) {
            $model->setAttribute('language', 'de');
        }
        if (array_key_exists('alt_text', $data)) {
            $model->setAttribute('alt_text', $data['alt_text'] === null ? null : trim((string) $data['alt_text']));
        }
        if ($model->getAttribute('is_decorative')) {
            $model->setAttribute('alt_text', '');
        }
        if ($this->applyingProposal) {
            return;
        }
        $file = $request->file('file');
        if ($file instanceof UploadedFile) {
            app(MediaStorage::class)->attach($model, $file);
            $path = $model->file_path;
            $this->onFailure[] = function () use ($path): void {
                Storage::disk((string) config('uploads.disk'))->delete($path);
            };
        }
    }

    public function beforeForceDelete(Model $model): void
    {
        if (app(ContentUsage::class)->of($model) !== []) {
            throw new DomainRuleViolation('Das Medium wird noch verwendet (einschließlich Versionen oder Vorschlägen).');
        }
    }

    public function afterForceDelete(Model $model): void
    {
        app(MediaStorage::class)->deleteFileAfterCommit($model);
    }

    public function columns(Model $model): array
    {
        return ['Datei' => $model->original_filename, 'Bildzweck' => $model->is_decorative ? 'Dekorativ' : ($model->alt_text ? 'Alternativtext vorhanden' : 'Nicht geprüft')];
    }
}
