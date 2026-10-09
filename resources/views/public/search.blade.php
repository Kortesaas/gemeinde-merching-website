@extends('layouts.public', ['robots' => 'noindex, follow'])
@section('title', 'Suche')
@section('content')
<h1>{{ $phrase !== '' ? 'Suchergebnisse für „'.$phrase.'“' : 'Website durchsuchen' }}</h1>
@include('public.partials.search-form', ['searchId'=>'results-search', 'searchValue'=>$phrase])
<form class="filter-form" method="GET" action="{{ route('public.search') }}">
    <input type="hidden" name="q" value="{{ $phrase }}">
    <x-form.select name="type" label="Inhaltstyp" :value="$type" :options="\App\Http\Controllers\Public\SearchController::LABELS" placeholder="Alle Inhalte" />
    <button class="button button--secondary" type="submit">Filtern</button>
</form>
@if ($phrase === '')<div class="empty-state"><p>Geben Sie ein Anliegen oder einen Suchbegriff ein.</p><a href="{{ app(\App\Services\Content\PublicCatalog::class)->sectionPath('az') }}">Leistungen von A bis Z</a></div>
@elseif ($records->isEmpty())<div class="empty-state"><h2>Keine Ergebnisse gefunden</h2><p>Prüfen Sie die Schreibweise oder versuchen Sie einen kürzeren Begriff. Auch gepflegte Synonyme und Suchbegriffe werden berücksichtigt.</p><a href="{{ app(\App\Services\Content\PublicCatalog::class)->sectionPath('services') }}">Zum Bürgerservice</a></div>
@else
<p class="meta">{{ $records->total() }} Ergebnisse</p>
<ul class="result-list">@foreach ($records as $result)<li><p class="meta">{{ \App\Http\Controllers\Public\SearchController::LABELS[$result['type']] ?? 'Inhalt' }}</p><h2><a href="{{ $result['url'] }}">{{ $result['title'] }}</a></h2>@if ($result['summary'])<p>{{ $result['summary'] }}</p>@endif</li>@endforeach</ul>
{{ $records->links('admin.partials.pagination') }}
@endif
@endsection
