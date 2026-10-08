<x-form.field :name="$field->name" :label="$field->label.($field->required ? ' (Pflichtfeld)' : '')"
    :type="$field->inputType" :value="$value" :hint="$field->hint"
    maxlength="{{ $field->max }}" :required="$field->required" :disabled="$disabled"
    autocomplete="{{ $field->autocomplete ?? 'off' }}" />
