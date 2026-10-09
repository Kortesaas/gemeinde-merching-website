{{-- Publication lifecycle. Status changes need publish (and archive) permission. --}}
@php
    $current = $model->exists ? $model->status : \App\Enums\PublicationStatus::Draft;
    $targets = collect([$current, ...$current->allowedTransitions()])
        ->filter(fn ($s) => $canArchive || ($s !== \App\Enums\PublicationStatus::Archived && $current !== \App\Enums\PublicationStatus::Archived) || $s === $current)
        ->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all();
    $dateField = fn (string $name, string $label) => \App\Admin\Fields\DateTime::make($name, $label);
@endphp
<fieldset class="form-fieldset cms-panel" id="publication">
    <legend class="form-label">Veröffentlichung</legend>
    <p>Aktueller Status: <strong>@include('admin.resources.partials.state', ['model' => $model])</strong></p>
    @if ($canPublish && ! $disabled)
        <x-form.select name="status" label="Status" :options="$targets" :value="old('status', $current->value)"
            hint="„Veröffentlicht“ wird ab dem Veröffentlichungsdatum sichtbar (sofort, wenn leer) und endet automatisch am Enddatum." />
    @elseif (! $disabled)
        <p class="form-hint">Sie können Entwürfe speichern. Die Veröffentlichung erfolgt durch eine Person mit Veröffentlichungsrecht.</p>
    @endif
    @foreach (['publish_at' => 'Veröffentlichen ab', 'expires_at' => 'Veröffentlichen bis'] as $name => $label)
        @include('admin.fields.datetime', [
            'field' => $dateField($name, $label),
            'value' => old($name, $model->exists && $model->{$name} ? \App\Support\SiteTime::toInput($model->{$name}) : ''),
            'disabled' => $disabled || ($model->exists && $model->isPublicationLocked() && ! $canPublish),
        ])
    @endforeach
</fieldset>
