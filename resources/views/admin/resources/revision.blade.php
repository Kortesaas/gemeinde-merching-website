@extends('layouts.admin')

@section('title', 'Version '.$revision->revision_number.' – '.$model->displayTitle())

@section('content')
    <div class="editor-bar">
        <a class="editor-bar__back" href="{{ route('admin.'.$resource->key().'.revisions', $model->getKey()) }}"><x-icon name="arrow-left" /> Versionsgeschichte</a>
        <span class="editor-bar__meta">{{ \App\Support\SiteTime::format($revision->created_at) }} · {{ $revision->user?->name ?? 'System' }}</span>
    </div>
    <p class="cms-eyebrow">Version {{ $revision->revision_number }}</p>
    <h1 class="editor-title">{{ $model->displayTitle() }}</h1>
    @if ($revision->summary)<p class="cms-lead">„{{ $revision->summary }}“</p>@endif

    @can('restoreRevision', $model)
        <details class="cms-panel restore-panel"><summary>Wiederherstellung bestätigen</summary><form data-confirm="Diese Version als neue Version wiederherstellen?" method="POST" action="{{ route('admin.'.$resource->key().'.revisions.restore', [$model->getKey(), $revision->revision_number]) }}">
            @csrf
            <p class="form-hint">Der Inhalt dieser Version wird als neue Version übernommen. Der Veröffentlichungsstatus bleibt unverändert; die Geschichte bleibt vollständig erhalten.</p>
            <button type="submit" class="button">Diese Version wiederherstellen</button>
        </form></details>
    @endcan

    <section class="cms-panel" aria-labelledby="snapshot-heading">
        <header class="cms-panel__header"><h2 id="snapshot-heading">Inhalt dieser Version</h2></header>
    <dl class="summary-list snapshot-list">
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
    </section>
@endsection
