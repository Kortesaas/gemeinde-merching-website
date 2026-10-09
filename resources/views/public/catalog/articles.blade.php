@include('public.catalog.filters', ['searchLabel' => 'Meldungen durchsuchen'])
@include('public.catalog.results-count', ['singular' => 'Meldung', 'plural' => 'Meldungen'])
@if ($records->isEmpty())
    <div class="empty-state"><h2>Keine Meldungen gefunden</h2><p>{{ $filtered ? 'Bitte ändern Sie die Filter.' : ($archive ? 'Das Archiv enthält derzeit keine Meldungen.' : 'Zurzeit gibt es keine aktuellen Meldungen.') }}</p></div>
@else
    <ul class="article-grid">
        @foreach ($records as $record)
            <li class="{{ $loop->first && $records->onFirstPage() && ! $filtered && ! $archive ? 'article-grid__featured' : '' }}">@include('public.partials.article-card', ['record' => $record, 'featured' => $loop->first && $records->onFirstPage() && ! $filtered && ! $archive])</li>
        @endforeach
    </ul>
    @include('public.partials.pagination', ['paginator' => $records])
@endif
