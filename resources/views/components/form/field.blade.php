{{--
    Accessible text input: visible <label>, optional hint and error message,
    both connected via aria-describedby; aria-invalid on error. The error is
    conveyed by text (prefixed "Fehler:"), not by colour alone.
--}}
@props([
    'name',
    'label',
    'type' => 'text',
    'hint' => null,
    'value' => null,
    'id' => null,
])
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
    <input
        {{ $attributes->class(['form-input', 'form-input--error' => $hasError]) }}
        id="{{ $id }}"
        name="{{ $name }}"
        type="{{ $type }}"
        @if ($value !== null && $type !== 'password') value="{{ $value }}" @endif
        @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
        @if ($hasError) aria-invalid="true" @endif
    >
</div>
