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
        @foreach (($revision->snapshot['collections'] ?? []) as $collection => $rows)
            <dt>{{ $labels[$collection] ?? $collection }}</dt>
            @php $collectionField = collect($resource->formFields($model))->first(fn ($field) => $field->name === $collection); @endphp
            <dd class="preserve-lines">{{ $collectionField ? $collectionField->snapshotDisplay(app(\App\Services\Content\ProposalDiff::class)->preview($model, $revision->snapshot), $revision->snapshot) : count($rows).' Einträge' }}</dd>
        @endforeach
    </dl>

    @can('restoreRevision', $model)
        <details class="cms-panel"><summary>Wiederherstellung bestätigen</summary><form data-confirm="Diese Version als neue Version wiederherstellen?" method="POST" action="{{ route('admin.'.$resource->key().'.revisions.restore', [$model->getKey(), $revision->revision_number]) }}">
            @csrf
            <p class="form-hint">Der Inhalt dieser Version wird als neue Version übernommen. Der Veröffentlichungsstatus bleibt unverändert; die Geschichte bleibt vollständig erhalten.</p>
            <button type="submit" class="button">Diese Version wiederherstellen</button>
        </form></details>
    @endcan
@endsection
