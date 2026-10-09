{{-- Publication lifecycle. Status changes need publish (and archive) permission. --}}
@php
    $current = $model->exists ? $model->status : \App\Enums\PublicationStatus::Draft;
    $targets = collect([$current, ...$current->allowedTransitions()])
        ->filter(fn ($s) => $canArchive || ($s !== \App\Enums\PublicationStatus::Archived && $current !== \App\Enums\PublicationStatus::Archived) || $s === $current)
        ->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all();
    $dateField = fn (string $name, string $label) => \App\Admin\Fields\DateTime::make($name, $label);
@endphp
<fieldset class="editor-card" id="publication">
    <legend class="editor-card__title">Veröffentlichung</legend>
    <div class="editor-card__body">
        <p class="publication-state">Aktuell: @include('admin.partials.state-badge', ['model' => $model])</p>
        @if ($canPublish && ! $disabled)
            <x-form.select name="status" label="Status" :options="$targets" :value="old('status', $current->value)"
                hint="„Veröffentlicht“ wird ab dem Startdatum sichtbar (sofort, wenn leer) und endet automatisch am Enddatum." />
        @elseif (! $disabled)
            <p class="form-hint">Sie können Entwürfe speichern. Veröffentlichen kann eine Person mit Veröffentlichungsrecht.</p>
        @endif
        <div class="field-pair">
            @foreach (['publish_at' => 'Sichtbar ab', 'expires_at' => 'Sichtbar bis'] as $name => $label)
                @include('admin.fields.datetime', [
                    'field' => $dateField($name, $label),
                    'value' => old($name, $model->exists && $model->{$name} ? \App\Support\SiteTime::toInput($model->{$name}) : ''),
                    'disabled' => $disabled || ($model->exists && $model->isPublicationLocked() && ! $canPublish),
                ])
            @endforeach
        </div>
    </div>
</fieldset>
