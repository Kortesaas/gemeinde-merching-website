@extends('layouts.admin')

@php
    $key = $resource->key();
    $isNew = ! $model->exists;
    $trashed = method_exists($model, 'trashed') && $model->trashed();
    $disabled = ! $editable || $trashed;
@endphp

@section('title', $isNew ? $resource->label().' anlegen' : $model->displayTitle().' – '.$resource->pluralLabel())

@section('content')
    <p><a href="{{ route('admin.'.$key.'.index') }}">Zurück zu {{ $resource->pluralLabel() }}</a></p>
    <h1>{{ $isNew ? $resource->label().' anlegen' : $model->displayTitle() }}</h1>

    <x-status />
    <x-form.error-summary />

    @if ($trashed)
        <div class="notice notice--warning" role="status"><p>Dieser Eintrag liegt im Papierkorb und ist nicht öffentlich sichtbar.</p></div>
    @elseif (! $editable)
        <div class="notice" role="status">
            <p>Sie können diesen Eintrag ansehen, aber nicht ändern.
                @if (method_exists($model, 'isPublicationLocked') && $model->isPublicationLocked())
                    Veröffentlichte oder geplante Inhalte können nur mit Veröffentlichungsrecht direkt geändert werden.
                    @can('propose', $model) Sie können aber eine Änderung vorschlagen (siehe „Änderungsvorschläge“ unten). @endcan
                @endif
            </p>
        </div>
    @endif

    @unless ($isNew)
        @include('admin.resources.partials.quality')
        @include('admin.resources.partials.meta')
    @endunless

    <form method="POST" action="{{ $isNew ? route('admin.'.$key.'.store') : route('admin.'.$key.'.update', $model->getKey()) }}"
          @if (in_array($key, ['document','media'], true)) enctype="multipart/form-data" @endif novalidate>
        @csrf
        @unless ($isNew) @method('PUT') @endunless

        @if (in_array($key, ['document','media'], true))
            @include($key === 'media' ? 'admin.resources.partials.media-file' : 'admin.resources.partials.file')
        @endif

        @foreach ($fields as $field)
            @include($field->view(), ['field' => $field, 'model' => $model, 'value' => old($field->name, $field->formValue($model)), 'disabled' => $disabled])
        @endforeach

        @if ($resource->isRoutable())
            @include('admin.resources.partials.route')
        @endif

        @if ($resource->isPublishable())
            @include('admin.resources.partials.publication')
        @endif

        @if ($resource->hasRevisions() && ! $disabled)
            <x-form.field name="revision_summary" label="Änderungsnotiz (optional)" :value="old('revision_summary')" maxlength="255"
                hint="Kurze Beschreibung der Änderung für die Versionsgeschichte." autocomplete="off" />
        @endif

        @unless ($disabled)
            <button type="submit" class="button">{{ $isNew ? 'Anlegen' : 'Speichern' }}</button>
        @endunless
    </form>

    @unless ($isNew)
        @if ($model instanceof \App\Contracts\Proposable && $model->isPublicationLocked())
            @include('admin.resources.partials.proposals')
        @endif

        @if ($resource->hasPlacements() && ! $trashed)
            @include('admin.resources.partials.placements')
        @endif

        @if (in_array($key, ['document', 'external-resource','media'], true))
            @include('admin.resources.partials.usage')
        @endif

        @include('admin.resources.partials.actions')
    @endunless
@endsection
