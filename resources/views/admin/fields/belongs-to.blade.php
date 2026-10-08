<x-form.select :name="$field->name" :label="$field->label.($field->required ? ' (Pflichtfeld)' : '')"
    :options="$field->optionsFor($model)" :value="$value" :hint="$field->hint"
    placeholder="– keine Auswahl –" :required="$field->required" :disabled="$disabled" />
