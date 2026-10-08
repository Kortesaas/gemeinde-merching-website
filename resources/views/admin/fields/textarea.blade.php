<x-form.textarea :name="$field->name" :label="$field->label.($field->required ? ' (Pflichtfeld)' : '')"
    :value="$value" :hint="$field->hint" :rows="$field->rows ?? 4" :required="$field->required" :disabled="$disabled" />
