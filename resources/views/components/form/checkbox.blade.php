{{-- Single checkbox; a hidden "0" makes unchecking explicit. --}}
@props(['name', 'label', 'checked' => false, 'hint' => null, 'id' => null])
@php
    $id ??= $name;
    $hasError = isset($errors) && $errors->has($name);
    $describedBy = collect([$hint ? $id.'-hint' : null, $hasError ? $id.'-error' : null])->filter()->implode(' ');
@endphp
<div @class(['form-field', 'form-field--checkbox', 'form-field--error' => $hasError])>
    @if ($hasError)
        <p class="form-error" id="{{ $id }}-error"><span class="form-error__prefix">Fehler:</span> {{ $errors->first($name) }}</p>
    @endif
    <input type="hidden" name="{{ $name }}" value="0" @disabled($attributes->get('disabled'))>
    <input {{ $attributes->class(['form-checkbox']) }} type="checkbox" id="{{ $id }}" name="{{ $name }}" value="1" @checked($checked)
        @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif>
    <label class="form-checkbox-label" for="{{ $id }}">{{ $label }}</label>
    @if ($hint)
        <p class="form-hint" id="{{ $id }}-hint">{{ $hint }}</p>
    @endif
</div>
