{{-- Public URL path, independent of navigation and IDs. --}}
@php
    $aliases = $model->exists ? $model->publicRoutes()->where('is_canonical', false)->orderBy('path')->pluck('path') : collect();
@endphp
@php
    $auto = $model::createsRouteAutomatically();
    $hint = $auto
        ? 'z. B. '.($model::defaultPathPrefix() ?: '').'/beispiel – ohne Schrägstrich am Ende. Leer lassen für einen automatischen Vorschlag; bestehende Adressen bleiben erhalten. Bei einer Änderung leitet die bisherige Adresse automatisch weiter.'
        : 'Optional. Nur ausfüllen, wenn dieser Eintrag eine eigene öffentliche Seite benötigt (z. B. eine übernommene alte Adresse). Leer lassen bzw. leeren = keine eigene Seite.';
@endphp
<x-form.field name="public_path" :label="$auto ? 'Öffentliche Adresse (Pfad)' : 'Eigene öffentliche Seite (Pfad, optional)'"
    :value="old('public_path', $model->exists ? $model->publicPath() : '')" :hint="$hint"
    maxlength="255" autocomplete="off" spellcheck="false" :disabled="$disabled" />
@if ($aliases->isNotEmpty())
    <p class="form-hint">Frühere Adressen (leiten weiter): @foreach ($aliases as $alias)<code>{{ $alias }}</code>@if (! $loop->last), @endif @endforeach</p>
@endif
