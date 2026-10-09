@extends('layouts.admin')
@section('title', 'Alle Inhalte')
@section('content')
<h1>Alle Inhalte</h1><p>Die letzten 50 Einträge je Bereich. Vollständige Listen finden Sie im jeweiligen Bereich.</p>
<form method="GET" class="filter-form" role="search" aria-label="Inhalte durchsuchen"><x-form.field name="q" label="Titel oder Bezeichnung" type="search" :value="$search" /><x-form.select name="type" label="Bereich" :options="$types" :value="$type" placeholder="Alle Bereiche" /><button type="submit" class="button">Filtern</button></form>
<div class="table-wrapper" role="region" aria-label="Inhaltsübersicht" tabindex="0"><table class="data-table"><caption class="visually-hidden">Inhalte nach letzter Änderung</caption><thead><tr><th scope="col">Titel</th><th scope="col">Typ</th><th scope="col">Status</th><th scope="col">Geändert</th></tr></thead><tbody>
@forelse ($records as $row)<tr><td><a href="{{ route('admin.'.$row['resource']->key().'.edit', $row['record']->getKey()) }}">{{ $row['record']->displayTitle() }}</a></td><td>{{ $row['resource']->label() }}</td><td>@include('admin.resources.partials.state', ['model'=>$row['record']])</td><td>{{ \App\Support\SiteTime::format($row['record']->updated_at) }}</td></tr>@empty<tr><td colspan="4">Keine Einträge gefunden.</td></tr>@endforelse
</tbody></table></div>{{ $records->links('admin.partials.pagination') }}
@endsection
