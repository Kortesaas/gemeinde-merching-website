{{-- Accessible textarea: visible label, hint and error connected via aria-describedby. --}}
@props(['name', 'label', 'value' => null, 'hint' => null, 'id' => null, 'rows' => 4])
@php
    $id ??= $name;
    $hasError = isset($errors) && $errors->has($name);
    $describedBy = collect([$hint ? $id.'-hint' : null, $hasError ? $id.'-error' : null])->filter()->implode(' ');
@endphp
<div @class(['form-field', 'form-field--error' => $hasError])>
    <label class="form-label" for="{{ $id }}">{{ $label }}</label>
    @if ($hint)
        <p class="form-hint" id="{{ $id }}-hint">{{ $hint }}</p>
    @endif
    @if ($hasError)
        <p class="form-error" id="{{ $id }}-error"><span class="form-error__prefix">Fehler:</span> {{ $errors->first($name) }}</p>
    @endif
    <textarea
        {{ $attributes->class(['form-input', 'form-textarea', 'form-input--error' => $hasError]) }}
        id="{{ $id }}" name="{{ $name }}" rows="{{ $rows }}"
        @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
        @if ($hasError) aria-invalid="true" @endif
    >{{ $value }}</textarea>
</div>
