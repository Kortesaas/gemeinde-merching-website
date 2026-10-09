{{-- Publication lifecycle. Status changes need publish (and archive) permission. --}}
@php
    $current = $model->exists ? $model->status : \App\Enums\PublicationStatus::Draft;
    $targets = collect([$current, ...$current->allowedTransitions()])
        ->filter(fn ($s) => $canArchive || ($s !== \App\Enums\PublicationStatus::Archived && $current !== \App\Enums\PublicationStatus::Archived) || $s === $current)
        ->mapWithKeys(fn ($s) => [$s->value => $s === \App\Enums\PublicationStatus::Published ? 'Veröffentlicht' : $s->label()])->all();
    $dateField = fn (string $name, string $label) => \App\Admin\Fields\DateTime::make($name, $label);
@endphp
<fieldset class="editor-card" id="publication">
    <legend class="editor-card__title">Veröffentlichung</legend>
    <div class="editor-card__body">
        <p class="publication-state">Aktuell: @include('admin.partials.state-badge', ['model' => $model])</p>
        @if ($canPublish && ! $disabled)
            <x-form.select name="status" label="Nach dem Speichern" data-timezone="{{ \App\Support\SiteTime::timezone() }}" data-public-archive="{{ $model->keepsPublicArchive() ? '1' : '0' }}" :options="$targets" :value="old('status', $current->value)"
                :hint="$model->keepsPublicArchive() ? 'Entwurf: nur intern. Archiviert: aus aktuellen Listen entfernt; frühere Veröffentlichungen bleiben erreichbar.' : 'Entwurf: nur intern. Veröffentlicht: öffentlich sichtbar. Archiviert: nicht mehr öffentlich.'" />
        @elseif (! $disabled)
            <p class="form-hint">Sie können Entwürfe speichern. Veröffentlichen kann eine Person mit Veröffentlichungsrecht.</p>
        @endif
        <details class="publication-schedule" @if ($errors->hasAny(['publish_at', 'expires_at']) || old('publish_at', $model->publish_at) || old('expires_at', $model->expires_at)) open @endif>
            <summary>Zeitraum festlegen <x-icon name="chevron-down" /></summary>
            <p class="form-hint">Leer: sofort sichtbar, ohne Enddatum.@if ($model->keepsPublicArchive()) Nach dem Enddatum bleiben frühere Veröffentlichungen öffentlich erreichbar.@endif</p>
        <div class="field-pair">
            @foreach (['publish_at' => 'Sichtbar ab', 'expires_at' => 'Sichtbar bis'] as $name => $label)
                @include('admin.fields.datetime', [
                    'field' => $dateField($name, $label),
                    'value' => old($name, $model->exists && $model->{$name} ? \App\Support\SiteTime::toInput($model->{$name}) : ''),
                    'disabled' => $disabled || ($model->exists && $model->isPublicationLocked() && ! $canPublish),
                ])
            @endforeach
        </div>
        </details>
    </div>
</fieldset>
