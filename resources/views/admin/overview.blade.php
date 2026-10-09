@extends('layouts.admin')
@section('title', 'Alle Inhalte')
@section('content')
<header class="cms-page-header">
    <div><p class="cms-eyebrow"><a href="{{ route('admin.dashboard') }}">Dashboard</a></p><h1>Alle Inhalte</h1></div>
</header>
<p class="cms-lead">Die zuletzt geänderten Einträge aller Bereiche, für die Sie Leserechte haben (bis zu 50 je Bereich). Vollständige Listen finden Sie im jeweiligen Bereich.</p>
<form method="GET" class="cms-filter" role="search" aria-label="Inhalte durchsuchen">
    <div class="cms-filter__search">
        <label class="form-label" for="q">Titel oder Bezeichnung</label>
        <div class="cms-input-icon"><x-icon name="search" /><input class="form-input" id="q" name="q" type="search" value="{{ $search }}" autocomplete="off"></div>
    </div>
    <x-form.select name="type" label="Bereich" :options="$types" :value="$type" placeholder="Alle Bereiche" />
    <button type="submit" class="button button--secondary">Filtern</button>
    @if ($search !== '' || $type !== '')<a class="cms-filter__reset" href="{{ route('admin.overview') }}">Zurücksetzen</a>@endif
</form>
<p class="cms-count">{{ $records->total() }} {{ $records->total() === 1 ? 'Eintrag' : 'Einträge' }}</p>
<div class="table-wrapper" role="region" aria-label="Inhaltsübersicht, Tabelle" tabindex="0">
    <table class="data-table">
        <caption class="visually-hidden">Inhalte nach letzter Änderung</caption>
        <thead><tr><th scope="col">Titel</th><th scope="col">Bereich</th><th scope="col">Status</th><th scope="col">Bearbeitet von</th><th scope="col">Geändert</th></tr></thead>
        <tbody>
            @forelse ($records as $row)
                <tr>
                    <th scope="row"><a href="{{ route('admin.'.$row['resource']->key().'.edit', $row['record']->getKey()) }}">{{ $row['record']->displayTitle() }}</a></th>
                    <td>{{ $row['resource']->label() }}</td>
                    <td>@include('admin.partials.state-badge', ['model' => $row['record']])</td>
                    <td>{{ method_exists($row['record'], 'editor') ? ($row['record']->editor?->name ?? '–') : '–' }}</td>
                    <td class="data-table__date">{{ \App\Support\SiteTime::format($row['record']->updated_at) }}</td>
                </tr>
            @empty
                <tr><td colspan="5">Keine Einträge gefunden.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
{{ $records->links('admin.partials.pagination') }}
@endsection
