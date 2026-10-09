@if ($paginator->hasPages())
    <nav class="pager" aria-label="Seitennavigation">
        @if (! $paginator->onFirstPage())<a class="button button--secondary" href="{{ $paginator->previousPageUrl() }}" rel="prev"><x-icon name="arrow-left" /> Vorherige Seite</a>@endif
        <p>Seite {{ $paginator->currentPage() }} von {{ $paginator->lastPage() }}</p>
        @if ($paginator->hasMorePages())<a class="button button--secondary" href="{{ $paginator->nextPageUrl() }}" rel="next">Nächste Seite <x-icon name="arrow-right" /></a>@endif
    </nav>
@endif
