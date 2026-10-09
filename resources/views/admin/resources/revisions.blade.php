@extends('layouts.admin')

@section('title', 'Versionen – '.$model->displayTitle())

@section('content')
    <p><a href="{{ route('admin.'.$resource->key().'.edit', $model->getKey()) }}">Zurück zu „{{ $model->displayTitle() }}“</a></p>
    <h1>Versionsgeschichte: {{ $model->displayTitle() }}</h1>

    @if ($revisions->isEmpty())
        <p>Noch keine Versionen vorhanden.</p>
    @else
        <div class="table-wrapper" role="region" aria-label="Datentabelle, horizontal verschiebbar" tabindex="0">
            <table class="data-table">
                <caption class="visually-hidden">Versionen, neueste zuerst</caption>
                <thead><tr><th scope="col">Version</th><th scope="col">Zeitpunkt</th><th scope="col">Bearbeitet von</th><th scope="col">Notiz</th></tr></thead>
                <tbody>
                    @foreach ($revisions as $revision)
                        <tr>
                            <td><a href="{{ route('admin.'.$resource->key().'.revisions.show', [$model->getKey(), $revision->revision_number]) }}">Version {{ $revision->revision_number }}</a></td>
                            <td>{{ \App\Support\SiteTime::format($revision->created_at) }}</td>
                            <td>{{ $revision->user?->name ?? 'System' }}</td>
                            <td>{{ $revision->summary }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
