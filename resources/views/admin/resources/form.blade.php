@extends('layouts.admin')

@php
    $key = $resource->key();
    $isNew = ! $model->exists;
    $trashed = method_exists($model, 'trashed') && $model->trashed();
    $disabled = ! $editable || $trashed;
    $fieldGroups = \App\Support\Content\EditorSections::group($fields);
    $multipart = in_array($key, ['document', 'media'], true);
    $publicPath = ! $isNew && $resource->isRoutable() && method_exists($model, 'isPubliclyReachable') && $model->isPubliclyReachable() ? $model->publicPath() : null;
    $sectionId = fn (string $name) => 'bereich-'.\Illuminate\Support\Str::slug($name);
    $after = ! $isNew && ! $trashed && $resource->hasPlacements();
@endphp

@section('title', $isNew ? $resource->label().' anlegen' : $model->displayTitle().' – '.$resource->pluralLabel())

@section('content')
    <div class="editor-bar">
        <a class="editor-bar__back" href="{{ route('admin.'.$key.'.index') }}"><x-icon name="arrow-left" /> {{ $resource->pluralLabel() }}</a>
        @unless ($isNew)@include('admin.partials.state-badge', ['model' => $model])@endunless
        @unless ($isNew)<span class="editor-bar__meta">Geändert {{ $model->updated_at?->locale('de')->diffForHumans() }}@if (($model->getAttributes()['updated_by'] ?? null) !== null) von {{ $model->editor?->name }}@endif</span>@endunless
        <div class="editor-bar__actions">
            @if ($publicPath)<a class="button button--secondary" href="{{ \App\Support\Routing\PublicPath::toUrl($publicPath) }}"><x-icon name="eye" /> Ansehen<span class="visually-hidden"> (öffentliche Seite)</span></a>@endif
            @if (! $isNew && $resource->hasRevisions())<a class="button button--secondary" href="{{ route('admin.'.$key.'.revisions', $model->getKey()) }}"><x-icon name="history" /> Versionen</a>@endif
            @unless ($disabled)<button type="submit" form="editor-form" class="button">{{ $isNew ? 'Anlegen' : 'Speichern' }}</button>@endunless
        </div>
    </div>

    <h1 class="editor-title">{{ $isNew ? $resource->label().' anlegen' : $model->displayTitle() }}</h1>

    <x-status />
    <x-form.error-summary />

    @if ($trashed)
        <div class="notice notice--warning" role="status"><p>Dieser Eintrag liegt im Papierkorb und ist nicht öffentlich sichtbar.</p></div>
    @elseif (! $editable)
        <div class="notice" role="status">
            <p>Sie können diesen Eintrag ansehen, aber nicht ändern.
                @if (method_exists($model, 'isPublicationLocked') && $model->isPublicationLocked())
                    Veröffentlichte oder geplante Inhalte ändern nur Personen mit Veröffentlichungsrecht direkt.
                    @can('propose', $model) Sie können aber eine <a href="#vorschlaege">Änderung vorschlagen</a>. @endcan
                @endif
            </p>
        </div>
    @endif

    <nav class="editor-tabs" aria-label="Bereiche dieses Eintrags">
        <ul>
            @if ($multipart)<li><a href="#datei">Datei</a></li>@endif
            @foreach ($fieldGroups as $section => $group)<li><a href="#{{ $sectionId($section) }}">{{ $section }}</a></li>@endforeach
            @if ($after)<li><a href="#documents">Downloads &amp; Links</a></li>@endif
            @if ($resource->isPublishable())<li><a href="#publication">Veröffentlichung</a></li>@endif
            @if ($resource->isRoutable())<li><a href="#public-route">URL</a></li>@endif
            @unless ($isNew)<li><a href="#quality">Qualität</a></li>@endunless
            @if (! $isNew && $resource->hasRevisions())<li><a href="{{ route('admin.'.$key.'.revisions', $model->getKey()) }}">Historie</a></li>@endif
        </ul>
    </nav>

    <form id="editor-form" class="editor-layout" method="POST" action="{{ $isNew ? route('admin.'.$key.'.store') : route('admin.'.$key.'.update', $model->getKey()) }}" @if ($multipart) enctype="multipart/form-data" @endif novalidate>
        @csrf
        <input type="hidden" name="_form_started" value="1">
        @unless ($isNew) @method('PUT') @endunless

        <div class="editor-main">
            @if ($multipart)
                <section class="editor-card" id="datei" aria-labelledby="datei-heading">
                    <h2 class="editor-card__title" id="datei-heading">Datei</h2>
                    <div class="editor-card__body">@include($key === 'media' ? 'admin.resources.partials.media-file' : 'admin.resources.partials.file')</div>
                </section>
            @endif

            @foreach ($fieldGroups as $section => $group)
                <details class="editor-section" id="{{ $sectionId($section) }}" open>
                    <summary><span>{{ $section }}</span><x-icon name="chevron-down" class="editor-section__chevron" /></summary>
                    <div class="field-grid">
                        @foreach ($group as $field)
                            @include($field->view(), ['field' => $field, 'model' => $model, 'value' => old($field->name, $field->formValue($model)), 'disabled' => $disabled])
                        @endforeach
                    </div>
                </details>
            @endforeach
        </div>

        <aside class="editor-aside" aria-label="Qualität, Veröffentlichung und Adresse">
            @unless ($isNew) @include('admin.resources.partials.quality') @endunless

            @if ($resource->isPublishable())
                @include('admin.resources.partials.publication')
            @endif

            @if ($resource->isRoutable())
                @include('admin.resources.partials.route')
            @endif

            @unless ($disabled)
                <section class="editor-card editor-card--save" aria-label="Speichern">
                    @if ($resource->hasRevisions())
                        <x-form.field name="revision_summary" label="Änderungsnotiz (optional)" :value="old('revision_summary')" maxlength="255"
                            hint="Erscheint in der Versionsgeschichte, z. B. „Gebühren 2027 ergänzt“." autocomplete="off" />
                    @endif
                    <button type="submit" class="button button--block">{{ $isNew ? 'Anlegen' : 'Speichern' }}</button>
                </section>
            @endunless
        </aside>
        <input type="hidden" name="_form_complete" value="1">
    </form>

    @unless ($isNew)
        <div class="editor-after">
            @if ($model instanceof \App\Contracts\Proposable && $model->isPublicationLocked())
                @include('admin.resources.partials.proposals')
            @endif

            @if ($after)
                @include('admin.resources.partials.placements')
            @endif

            @if (in_array($key, ['document', 'external-resource', 'media', 'gallery', 'wahlperioden', 'ratsmitglieder', 'ausschuesse'], true))
                @include('admin.resources.partials.usage')
            @endif

            @include('admin.resources.partials.actions')
        </div>
    @endunless
@endsection
