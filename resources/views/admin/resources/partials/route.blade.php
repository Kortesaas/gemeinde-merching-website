{{-- Public URL path, independent of navigation and IDs. --}}
@php
    $aliases = $model->exists ? $model->publicRoutes()->where('is_canonical', false)->orderBy('path')->pluck('path') : collect();
@endphp
<x-form.field name="public_path" label="Öffentliche Adresse (Pfad)" :value="old('public_path', $model->exists ? $model->publicPath() : '')"
    hint="z. B. {{ $model::defaultPathPrefix() ?: '' }}/beispiel – leer lassen für einen automatischen Vorschlag. Bei einer Änderung leitet die bisherige Adresse automatisch weiter."
    maxlength="255" autocomplete="off" spellcheck="false" :disabled="$disabled" />
@if ($aliases->isNotEmpty())
    <p class="form-hint">Frühere Adressen (leiten weiter): @foreach ($aliases as $alias)<code>{{ $alias }}</code>@if (! $loop->last), @endif @endforeach</p>
@endif
