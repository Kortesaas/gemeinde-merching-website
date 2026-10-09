@include('public.catalog.filters', ['searchLabel' => 'Meldungen durchsuchen'])
@include('public.catalog.results-count', ['singular' => 'Meldung', 'plural' => 'Meldungen'])
@if ($records->isEmpty())
    <div class="empty-state"><h2>Keine Meldungen gefunden</h2><p>{{ $filtered ? 'Bitte ändern Sie die Filter.' : ($archive ? 'Das Archiv enthält derzeit keine Meldungen.' : 'Zurzeit gibt es keine aktuellen Meldungen.') }}</p></div>
@else
    <ul class="article-list">
        @foreach ($records as $record)
            @php $image = $record->media->first(fn ($m) => $m->isPubliclyReachable() && $m->isImage() && $m->hasAccessibleAlternative()); @endphp
            <li class="article-card {{ $image ? '' : 'article-card--text' }}">
                <div class="article-card__body">
                    <p class="meta"><time datetime="{{ $record->publish_at?->toIso8601String() }}">{{ \App\Support\SiteTime::formatLocalized($record->publish_at) }}</time>@if ($record->category) · {{ $record->category->name }}@endif</p>
                    <h2 class="article-card__title"><a href="{{ \App\Support\Routing\PublicPath::toUrl($record->publicPath()) }}">{{ $record->displayTitle() }}</a></h2>
                    @if ($record->summary)<p class="article-card__summary">{{ $record->summary }}</p>@endif
                </div>
                @if ($image)<div class="article-card__media">@include('public.partials.image', ['medium' => $image, 'imageAlt' => '', 'imageSizes' => '(max-width: 40rem) calc(100vw - 2rem), 18rem'])</div>@endif
            </li>
        @endforeach
    </ul>
    @include('public.partials.pagination', ['paginator' => $records])
@endif
