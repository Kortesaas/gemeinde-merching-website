<x-form.field :name="$field->name" :label="$field->label.($field->required ? ' (Pflichtfeld)' : '')"
    type="number" :value="$value" :hint="$field->hint" min="{{ $field->min }}" max="{{ $field->max }}" step="{{ $field->step }}"
    :required="$field->required" :disabled="$disabled" />
