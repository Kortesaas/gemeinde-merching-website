{{-- Checkbox group in a fieldset; sortable relations get an order number per selected entry. --}}
@php
    $options = $field->optionsFor($model);
    $selected = array_map('intval', (array) $value);
    $orders = (array) old($field->name.'_order', $field->sortable ? $field->orderValues($model) : []);
    $hasError = $errors->has($field->name) || $errors->has($field->name.'.*');
@endphp
<fieldset id="{{ $field->name }}" @class(['form-field', 'form-fieldset', 'form-field--error' => $hasError]) @if ($hasError) aria-describedby="{{ $field->name }}-error" @endif>
    <legend class="form-label">{{ $field->label }}</legend>
    @if ($field->hint)
        <p class="form-hint">{{ $field->hint }}</p>
    @endif
    @if ($hasError)
        <p class="form-error" id="{{ $field->name }}-error"><span class="form-error__prefix">Fehler:</span> {{ $errors->first($field->name) ?: $errors->first($field->name.'.*') }}</p>
    @endif
    <input type="hidden" name="{{ $field->name }}__present" value="1" @disabled($disabled)>
    @forelse ($options as $id => $label)
        <div class="form-option">
            <input class="form-checkbox" type="checkbox" id="{{ $field->name }}-{{ $id }}" name="{{ $field->name }}[]" value="{{ $id }}"
                @checked(in_array($id, $selected, true)) @disabled($disabled)>
            <label class="form-checkbox-label" for="{{ $field->name }}-{{ $id }}">{{ $label }}</label>
            @if ($field->sortable)
                <label class="visually-hidden" for="{{ $field->name }}-order-{{ $id }}">Reihenfolge für {{ $label }}</label>
                <input class="form-input form-input--small" type="number" min="0" max="65535" id="{{ $field->name }}-order-{{ $id }}"
                    name="{{ $field->name }}_order[{{ $id }}]" value="{{ $orders[$id] ?? '' }}" @disabled($disabled)>
            @endif
        </div>
    @empty
        <p class="form-hint">Noch keine Einträge vorhanden.</p>
    @endforelse
</fieldset>
