<?php

namespace App\Http\Requests\Admin;

use App\Admin\ContentResource;
use App\Admin\ResourceRegistry;
use App\Models\ContentProposal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Validator;

/**
 * Validates proposed content with the same field rules as a direct edit.
 * Publication fields, URL and file uploads are not part of a proposal.
 */
class ProposalRequest extends FormRequest
{
    public function proposal(): ContentProposal
    {
        /** @var ContentProposal */
        return $this->route('proposal');
    }

    public function record(): Model
    {
        return $this->proposal()->proposable ?? abort(404);
    }

    /**
     * @return ContentResource<Model>
     */
    public function resource(): ContentResource
    {
        return ResourceRegistry::forModel($this->record()) ?? abort(404);
    }

    public function authorize(): bool
    {
        return (bool) $this->user()?->can('update', $this->proposal());
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $record = $this->record();
        $rules = [];
        foreach ($this->resource()->formFields($record) as $field) {
            $rules += $field->validationRules($record);
        }

        return $rules + Arr::except($this->resource()->rules($record), ['file']) + [
            'proposal_summary' => ['nullable', 'string', 'max:255'],
            'action' => ['nullable', 'in:save,submit'],
        ];
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
        $names = ['proposal_summary' => 'Beschreibung der Änderung'];
        foreach ($this->resource()->formFields($this->record()) as $field) {
            $names[$field->name] = $field->label;
        }

        return $names;
    }
}
