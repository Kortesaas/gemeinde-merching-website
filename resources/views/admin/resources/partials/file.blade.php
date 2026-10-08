{{-- Document upload (validated by the secure upload pipeline). --}}
@php
    $hasError = $errors->has('file');
    $accept = collect(array_keys(config('uploads.types')))->map(fn ($e) => '.'.$e)->implode(',');
@endphp
@if ($model->exists)
    <dl class="summary-list summary-list--inline">
        <dt>Datei</dt>
        <dd>{{ $model->original_filename }} ({{ strtoupper($model->extension) }}, {{ number_format($model->size_bytes / 1024, 0, ',', '.') }} KB)
            – <a href="{{ route('admin.document.file', $model->getKey()) }}">herunterladen</a></dd>
    </dl>
@endif
<div @class(['form-field', 'form-field--error' => $hasError])>
    <label class="form-label" for="file">{{ $model->exists ? 'Datei ersetzen (optional)' : 'Datei (Pflichtfeld)' }}</label>
    <p class="form-hint" id="file-hint">Erlaubt: {{ strtoupper(implode(', ', array_keys(config('uploads.types')))) }}; max. {{ (int) (config('uploads.max_kilobytes') / 1024) }} MB. Der Dateiinhalt wird geprüft.</p>
    @if ($hasError)
        <p class="form-error" id="file-error"><span class="form-error__prefix">Fehler:</span> {{ $errors->first('file') }}</p>
    @endif
    <input class="form-input" type="file" id="file" name="file" accept="{{ $accept }}"
        aria-describedby="file-hint{{ $hasError ? ' file-error' : '' }}" @if ($hasError) aria-invalid="true" @endif
        @disabled($disabled) @required(! $model->exists)>
</div>
