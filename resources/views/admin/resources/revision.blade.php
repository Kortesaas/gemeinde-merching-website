@extends('layouts.admin')

@section('title', 'Version '.$revision->revision_number.' – '.$model->displayTitle())

@section('content')
    <p><a href="{{ route('admin.'.$resource->key().'.revisions', $model->getKey()) }}">Zurück zur Versionsgeschichte</a></p>
    <h1>Version {{ $revision->revision_number }}: {{ $model->displayTitle() }}</h1>
    <p>{{ \App\Support\SiteTime::format($revision->created_at) }}, {{ $revision->user?->name ?? 'System' }}@if ($revision->summary) – {{ $revision->summary }}@endif</p>

    <h2>Inhalt dieser Version</h2>
    <dl class="summary-list">
        @foreach ($revision->snapshot['attributes'] as $name => $value)
            <dt>{{ $labels[$name] ?? $name }}</dt>
            <dd class="preserve-lines">{{ $value === null || $value === '' ? '–' : (is_scalar($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE)) }}</dd>
        @endforeach
        @foreach ($revision->snapshot['relations'] as $relation => $rows)
            <dt>{{ $labels[$relation] ?? $relation }}</dt>
            <dd>{{ count($rows) }} Verknüpfung(en)</dd>
        @endforeach
    </dl>

    @can('restoreRevision', $model)
        <form method="POST" action="{{ route('admin.'.$resource->key().'.revisions.restore', [$model->getKey(), $revision->revision_number]) }}">
            @csrf
            <p class="form-hint">Der Inhalt dieser Version wird als neue Version übernommen. Der Veröffentlichungsstatus bleibt unverändert; die Geschichte bleibt vollständig erhalten.</p>
            <button type="submit" class="button">Diese Version wiederherstellen</button>
        </form>
    @endcan
@endsection
