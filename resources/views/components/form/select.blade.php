{{-- Accessible native select. --}}
@props(['name', 'label', 'options' => [], 'value' => null, 'hint' => null, 'id' => null, 'placeholder' => null])
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
    <select
        {{ $attributes->class(['form-input', 'form-select', 'form-input--error' => $hasError]) }}
        id="{{ $id }}" name="{{ $name }}"
        @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
        @if ($hasError) aria-invalid="true" @endif
    >
        @if ($placeholder !== null)
            <option value="">{{ $placeholder }}</option>
        @endif
        @foreach ($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}" @selected((string) $value === (string) $optionValue)>{{ $optionLabel }}</option>
        @endforeach
    </select>
</div>
