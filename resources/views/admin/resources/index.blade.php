@extends('layouts.admin')

@section('title', $resource->pluralLabel())

@section('content')
    <p><a href="{{ route('admin.dashboard') }}">Zur Übersicht</a></p>
    <h1>{{ $resource->pluralLabel() }}{{ $trash ? ' – Papierkorb' : '' }}</h1>

    <x-status />
    <x-form.error-summary />

    <ul class="nav-list">
        @can('create', $resource->model())
            @unless ($trash)
                <li><a class="button" href="{{ route('admin.'.$resource->key().'.create') }}">{{ $resource->label() }} anlegen</a></li>
            @endunless
        @endcan
        @if ($resource->usesRecycleBin())
            <li>
                @if ($trash)
                    <a href="{{ route('admin.'.$resource->key().'.index') }}">Zurück zur Liste</a>
                @else
                    <a href="{{ route('admin.'.$resource->key().'.index', ['papierkorb' => 1]) }}">Papierkorb anzeigen</a>
                @endif
            </li>
        @endif
    </ul>

    <form method="GET" action="{{ route('admin.'.$resource->key().'.index') }}" class="filter-form" role="search" aria-label="{{ $resource->pluralLabel() }} durchsuchen">
        @if ($trash)
            <input type="hidden" name="papierkorb" value="1">
        @endif
        <x-form.field name="q" label="Suche" type="search" :value="$search" autocomplete="off" />
        @if ($resource->isPublishable())
            <x-form.select name="status" label="Status" :value="$status?->value" placeholder="– alle –"
                :options="collect(\App\Enums\PublicationStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all()" />
        @endif
        <button type="submit" class="button button--secondary">Filtern</button>
    </form>

    @if ($records->isEmpty())
        <p>Keine Einträge gefunden.</p>
    @else
        @if ($resource->key() === 'media')
            <div class="media-grid">@foreach ($records as $medium)<article class="media-card">
                @if ($medium->isImage())<a aria-label="{{ $medium->title }} bearbeiten" href="{{ route('admin.media.edit', $medium->getKey()) }}"><img src="{{ route('admin.media.file', ['record'=>$medium->getKey(), 'width'=>480]) }}" alt="" width="{{ $medium->width }}" height="{{ $medium->height }}" loading="lazy"></a>@endif
                <h2><a href="{{ route('admin.media.edit', $medium->getKey()) }}">{{ $medium->title }}</a></h2><p>{{ $medium->hasAccessibleAlternative() ? ($medium->is_decorative ? 'Dekoratives Bild' : 'Alternativtext vorhanden') : 'Alternativtext fehlt' }}</p><p class="form-hint">{{ $medium->width }} × {{ $medium->height }} · {{ number_format($medium->size_bytes/1024,0,',','.') }} KB</p>
            </article>@endforeach</div>
        @endif
        <div class="table-wrapper" role="region" aria-label="Datentabelle, horizontal verschiebbar" tabindex="0">
            <table class="data-table">
                <caption class="visually-hidden">{{ $resource->pluralLabel() }}, {{ $records->total() }} Einträge</caption>
                <thead>
                    <tr>
                        <th scope="col">Bezeichnung</th>
                        @foreach ($resource->columns($records->first()) as $column => $unused)
                            <th scope="col">{{ $column }}</th>
                        @endforeach
                        <th scope="col">Status</th>
                        <th scope="col">Zuletzt geändert</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($records as $record)
                        <tr>
                            <td><a href="{{ route('admin.'.$resource->key().'.edit', $record->getKey()) }}">{{ $record->displayTitle() }}</a></td>
                            @foreach ($resource->columns($record) as $value)
                                <td>{{ $value }}</td>
                            @endforeach
                            <td>@include('admin.resources.partials.state', ['model' => $record])</td>
                            <td>{{ \App\Support\SiteTime::format($record->updated_at) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        {{ $records->links('admin.partials.pagination') }}
    @endif
@endsection
