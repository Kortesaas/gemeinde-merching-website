<x-form.select :name="$field->name" :label="$field->label.($field->required ? ' (Pflichtfeld)' : '')"
    :options="$field->options" :value="$value" :hint="$field->hint"
    :placeholder="$field->required ? null : '– keine Auswahl –'" :required="$field->required" :disabled="$disabled" />
