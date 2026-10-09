@include('public.catalog.filters', ['searchLabel' => 'Dokumente durchsuchen'])
@include('public.catalog.results-count', ['singular' => 'Dokument', 'plural' => 'Dokumente'])
@if ($archive)<p class="notice-box"><x-icon name="history" /> Archiv: Ältere oder abgelaufene Fassungen bleiben zur Nachvollziehbarkeit abrufbar.</p>@endif
@if ($records->isEmpty())
    <div class="empty-state"><h2>Keine Dokumente gefunden</h2><p>{{ $filtered ? 'Bitte ändern Sie die Filter.' : 'Zurzeit sind keine Dokumente veröffentlicht.' }}</p></div>
@else
    <ul class="download-list download-list--wide">@foreach ($records as $record)<li>@include('public.partials.download-item', ['document' => $record, 'headingTag' => 'h2'])</li>@endforeach</ul>
    @include('public.partials.pagination', ['paginator' => $records])
@endif
