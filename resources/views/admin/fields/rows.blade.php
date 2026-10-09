<fieldset id="{{ $field->name }}" data-row-editor="{{ $field->rowDefinition }}">
    <legend>{{ $field->label }}</legend>
    <p>Die Position bestimmt die Reihenfolge. Bei gleicher Position gilt die Eingabereihenfolge. Neue Einträge in den freien Zeilen ergänzen und speichern; weitere freie Zeilen erscheinen danach. Alles funktioniert mit Tastatur und ohne JavaScript.</p>
    @if ($errors->has($field->name))<p class="form-error" role="alert">{{ $errors->first($field->name) }}</p>@endif
    <input type="hidden" name="{{ $field->name }}__present" value="1" @disabled($disabled)>
    @php $rows = is_array($value) ? $value : []; $columns = $field->columns(); @endphp
    @foreach ([...$rows, ...($disabled ? [] : [[], [], []])] as $index => $row)
        <fieldset class="enhanced-row {{ empty($row) ? 'row-add-slot' : '' }}" data-editor-row>
            <legend>{{ $field->label }} – Eintrag {{ $index + 1 }}</legend>
            <div class="row-grid">
            @foreach ($columns as $column => $definition)
                @php $id = $field->name.'_'.$index.'_'.$column; $inputName = $field->name.'['.$index.']['.$column.']'; $rowValue = $row[$column] ?? ($column === 'sort_order' ? $index : ''); @endphp
                <div class="form-field" data-row-column="{{ $column }}">
                    <label class="form-label" for="{{ $id }}">{{ $definition['label'] }}</label>
                    @if (isset($definition['options']))
                        <select class="form-input" id="{{ $id }}" name="{{ $inputName }}" @disabled($disabled)>
                            <option value="">– keine Auswahl –</option>
                            @foreach ($definition['options'] as $option => $label)<option value="{{ $option }}" @selected((string) $rowValue === (string) $option)>{{ $label }}</option>@endforeach
                        </select>
                    @elseif (! empty($definition['multiline']))
                        <textarea class="form-input" id="{{ $id }}" name="{{ $inputName }}" rows="4" @disabled($disabled)>{{ $rowValue }}</textarea>
                    @else
                        <input class="form-input" id="{{ $id }}" name="{{ $inputName }}" value="{{ $rowValue }}" type="{{ $definition['type'] ?? 'text' }}" @if(($definition['type'] ?? '') === 'number') min="0" step="{{ $column === 'amount' ? '0.01' : '1' }}" @endif @disabled($disabled)>
                    @endif
                </div>
            @endforeach
            </div>
            <label><input type="checkbox" name="{{ $field->name }}[{{ $index }}][_remove]" value="1" @disabled($disabled)> Diesen Eintrag beim Speichern entfernen</label>
        </fieldset>
    @endforeach
</fieldset>
