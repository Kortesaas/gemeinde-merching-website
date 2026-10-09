{{-- Public URL path, independent of navigation and IDs. --}}
@php
    $aliases = $model->exists ? $model->publicRoutes()->where('is_canonical', false)->orderBy('path')->pluck('path') : collect();
    $auto = $model::createsRouteAutomatically();
    $hint = $auto
        ? 'z. B. '.($model::defaultPathPrefix() ?: '').'/beispiel – ohne Schrägstrich am Ende. Leer lassen für einen Vorschlag aus dem Titel. Bei einer Änderung leitet die bisherige Adresse automatisch weiter.'
        : 'Optional. Nur ausfüllen, wenn dieser Eintrag eine eigene öffentliche Seite braucht (z. B. eine übernommene alte Adresse). Leer = keine eigene Seite.';
@endphp
<details class="editor-card editor-section" @if ($errors->has('public_path')) open @endif id="public-route" aria-labelledby="route-heading">
    <summary id="route-heading">Öffentliche Adresse <x-icon name="chevron-down" class="editor-section__chevron" /></summary>
    <div class="editor-card__body">
        <x-form.field name="public_path" :label="$auto ? 'Öffentliche Adresse' : 'Eigene öffentliche Seite (optional)'"
            :value="old('public_path', $model->exists ? $model->publicPath() : '')" :hint="$hint"
            maxlength="255" autocomplete="off" spellcheck="false" :disabled="$disabled" />
        @if ($aliases->isNotEmpty())
            <p class="form-hint">Frühere Adressen leiten weiter: @foreach ($aliases as $alias)<code>{{ $alias }}</code>@if (! $loop->last), @endif @endforeach</p>
        @endif
    </div>
</details>
