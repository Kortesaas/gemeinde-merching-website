<x-form.field :name="$field->name" :label="$field->label.($field->required ? ' (Pflichtfeld)' : '')"
    type="date" :value="$value" :hint="$field->hint" :required="$field->required" :disabled="$disabled" />
