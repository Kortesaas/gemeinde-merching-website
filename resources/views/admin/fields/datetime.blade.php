<x-form.field :name="$field->name" :label="$field->label.($field->required ? ' (Pflichtfeld)' : '')"
    type="datetime-local" :value="$value"
    :hint="trim(($field->hint ? $field->hint.' ' : '').'Ortszeit ('.\App\Support\SiteTime::timezone().').')"
    :required="$field->required" :disabled="$disabled" />
