@if ($paginator->hasPages())
    <nav class="pagination" aria-label="Seitennavigation">
        <p>Seite {{ $paginator->currentPage() }} von {{ $paginator->lastPage() }}</p>
        <ul class="nav-list">
            @if (! $paginator->onFirstPage())
                <li><a href="{{ $paginator->previousPageUrl() }}" rel="prev">Vorherige Seite</a></li>
            @endif
            @if ($paginator->hasMorePages())
                <li><a href="{{ $paginator->nextPageUrl() }}" rel="next">Nächste Seite</a></li>
            @endif
        </ul>
    </nav>
@endif
