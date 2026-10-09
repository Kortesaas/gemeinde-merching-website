@extends('layouts.admin')

@section('title', $resource->pluralLabel())

@php
    $key = $resource->key();
    $columns = $records->isNotEmpty() ? array_keys($resource->columns($records->first())) : [];
    $filtered = $search !== '' || $status !== null;
@endphp

@section('content')
    <header class="cms-page-header">
        <div>
            <p class="cms-eyebrow"><a href="{{ route('admin.dashboard') }}">Dashboard</a>@if ($trash) / <a href="{{ route('admin.'.$key.'.index') }}">{{ $resource->pluralLabel() }}</a>@endif</p>
            <h1>{{ $resource->pluralLabel() }}@if ($trash) <span class="state state--trashed">Papierkorb</span>@endif</h1>
        </div>
        <div class="cms-page-actions">
            @if ($resource->usesRecycleBin())
                @if ($trash)
                    <a class="button button--secondary" href="{{ route('admin.'.$key.'.index') }}"><x-icon name="arrow-left" /> Zurück zur Liste</a>
                @else
                    <a class="button button--secondary" href="{{ route('admin.'.$key.'.index', ['papierkorb' => 1]) }}"><x-icon name="trash" /> Papierkorb</a>
                @endif
            @endif
            @can('create', $resource->model())
                @unless ($trash)
                    <a class="button" href="{{ route('admin.'.$key.'.create') }}"><x-icon name="plus" /> {{ $resource->label() }} anlegen</a>
                @endunless
            @endcan
        </div>
    </header>

    <x-status />
    <x-form.error-summary />

    <form method="GET" action="{{ route('admin.'.$key.'.index') }}" class="cms-filter" role="search" aria-label="{{ $resource->pluralLabel() }} durchsuchen">
        @if ($trash)<input type="hidden" name="papierkorb" value="1">@endif
        <div class="cms-filter__search">
            <label class="form-label" for="q">Suche</label>
            <div class="cms-input-icon"><x-icon name="search" /><input class="form-input" id="q" name="q" type="search" value="{{ $search }}" autocomplete="off" placeholder="Titel oder Bezeichnung"></div>
        </div>
        @if ($resource->isPublishable())
            <x-form.select name="status" label="Status" :value="$status?->value" placeholder="Alle"
                :options="collect(\App\Enums\PublicationStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all()" />
        @endif
        <button type="submit" class="button button--secondary">Filtern</button>
        @if ($filtered)<a class="cms-filter__reset" href="{{ route('admin.'.$key.'.index', $trash ? ['papierkorb' => 1] : []) }}">Zurücksetzen</a>@endif
    </form>

    <p class="cms-count">{{ $records->total() }} {{ $records->total() === 1 ? 'Eintrag' : 'Einträge' }}@if ($filtered) gefunden @endif</p>

    @if ($records->isEmpty())
        <div class="cms-empty-state">
            <p><strong>{{ $filtered ? 'Keine passenden Einträge.' : ($trash ? 'Der Papierkorb ist leer.' : 'Hier gibt es noch keine Einträge.') }}</strong></p>
            @if (! $filtered && ! $trash)
                @can('create', $resource->model())<a class="button" href="{{ route('admin.'.$key.'.create') }}">{{ $resource->label() }} anlegen</a>@endcan
            @endif
        </div>
    @else
        @if ($key === 'media')
            <ul class="media-grid" aria-label="Bildvorschau">
                @foreach ($records as $medium)
                    <li class="media-card">
                        <a class="media-card__image" href="{{ route('admin.media.edit', $medium->getKey()) }}">
                            @if ($medium->isImage())<img src="{{ route('admin.media.file', ['record' => $medium->getKey(), 'width' => 480]) }}" alt="" width="{{ $medium->width }}" height="{{ $medium->height }}" loading="lazy">@endif
                            <span class="visually-hidden">{{ $medium->title }} bearbeiten</span>
                        </a>
                        <div class="media-card__body">
                            <p class="media-card__title">{{ $medium->title }}</p>
                            <p class="cms-row__meta">{{ $medium->width }} × {{ $medium->height }} · {{ \App\Support\Content\PublicFormat::fileSize($medium->size_bytes) }}</p>
                            @if ($medium->hasAccessibleAlternative())
                                <span class="severity severity--ok"><x-icon name="check" />{{ $medium->is_decorative ? 'Dekorativ' : 'Alternativtext' }}</span>
                            @else
                                @include('admin.partials.severity', ['severity' => \App\Enums\QualitySeverity::Error])<span class="cms-row__meta"> Alternativtext fehlt</span>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
        <div class="table-wrapper" role="region" aria-label="{{ $resource->pluralLabel() }}, Tabelle" tabindex="0">
            <table class="data-table data-table--stack" role="table">
                <caption class="visually-hidden">{{ $resource->pluralLabel() }}, {{ $records->total() }} Einträge</caption>
                <thead>
                    <tr role="row">
                        <th scope="col" role="columnheader">Bezeichnung</th>
                        @foreach ($columns as $column)<th scope="col" role="columnheader">{{ $column }}</th>@endforeach
                        <th scope="col" role="columnheader">Status</th>
                        <th scope="col" role="columnheader">Zuletzt geändert</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($records as $record)
                        <tr role="row">
                            <th scope="row" role="rowheader"><a href="{{ route('admin.'.$key.'.edit', $record->getKey()) }}">{{ $record->displayTitle() }}</a></th>
                            @foreach ($resource->columns($record) as $column => $value)<td role="cell" data-label="{{ $column }}">{{ $value }}</td>@endforeach
                            <td role="cell" data-label="Status">@include('admin.partials.state-badge', ['model' => $record])</td>
                            <td role="cell" class="data-table__date" data-label="Geändert">{{ \App\Support\SiteTime::format($record->updated_at) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        {{ $records->links('admin.partials.pagination') }}
    @endif
@endsection
