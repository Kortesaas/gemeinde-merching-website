<?php

namespace App\Http\Requests\Admin;

use App\Admin\ContentResource;
use App\Admin\ResourceRegistry;
use App\Enums\PublicationStatus;
use App\Support\SiteTime;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Validation and authorization of create/update for every admin resource.
 * Authorization runs first (403 before any validation):
 *
 * - create / update permission (ContentPolicy);
 * - any change of the publication status, or of the publication window of
 *   content that is not a draft, requires "publish"; entering or leaving the
 *   archive additionally requires "archive".
 */
class ResourceRequest extends FormRequest
{
    private ?Model $recordCache = null;

    /**
     * @return ContentResource<Model>
     */
    public function resource(): ContentResource
    {
        return ResourceRegistry::get((string) $this->route()?->defaults['resource']);
    }

    public function record(): ?Model
    {
        $id = $this->route('record');

        return $id === null ? null : ($this->recordCache ??= $this->resource()->find((int) $id));
    }

    public function authorize(): bool
    {
        $user = $this->user();
        $record = $this->record();
        $resource = $this->resource();

        if ($user === null || ! ($record ? $user->can('update', $record) : $user->can('create', $resource->model()))) {
            return false;
        }

        if (! $resource->isPublishable()) {
            return true;
        }

        /** @var PublicationStatus $current */
        $current = $record?->getAttribute('status') ?? PublicationStatus::Draft;
        $target = PublicationStatus::tryFrom((string) $this->input('status', $current->value)) ?? $current;

        if ($target !== $current) {
            if (! $user->can('publish', $record ?? $resource->model())) {
                return false;
            }
            if (($target === PublicationStatus::Archived || $current === PublicationStatus::Archived)
                && ! $user->can('archive', $record ?? $resource->model())) {
                return false;
            }
        }

        if ($record !== null && $current !== PublicationStatus::Draft && $this->publicationWindowChanged($record)) {
            return $user->can('publish', $record);
        }

        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return $this->resource()->validationRules($this->record());
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [fn (Validator $validator) => $this->resource()->validateAfter($validator, $this->record())];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $names = ['status' => 'Status', 'publish_at' => 'Veröffentlichen ab', 'expires_at' => 'Veröffentlichen bis', 'public_path' => 'Öffentliche Adresse'];
        foreach ($this->resource()->fields($this->record()) as $field) {
            $names[$field->name] = $field->label;
            $names[$field->name.'.*'] = $field->label;
        }

        return $names;
    }

    private function publicationWindowChanged(Model $record): bool
    {
        foreach (['publish_at', 'expires_at'] as $field) {
            if ($this->has($field)) {
                $current = $record->getAttribute($field);
                if ((string) $this->input($field) !== ($current ? SiteTime::toInput($current) : '')) {
                    return true;
                }
            }
        }

        return false;
    }
}
