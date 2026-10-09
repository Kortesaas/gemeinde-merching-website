@include('public.catalog.filters', ['searchLabel' => 'Bekanntmachungen durchsuchen'])
@include('public.catalog.results-count', ['singular' => 'Bekanntmachung', 'plural' => 'Bekanntmachungen'])
@if ($records->isEmpty())
    <div class="empty-state"><h2>Keine Bekanntmachungen gefunden</h2><p>{{ $filtered ? 'Bitte ändern Sie die Filter.' : 'Zurzeit sind keine Bekanntmachungen veröffentlicht.' }}</p></div>
@else
    <ul class="notice-list">
        @foreach ($records as $record)
            <li class="notice-row">
                <p class="notice-row__date"><span class="visually-hidden">Bekannt gemacht am </span>{{ \App\Support\SiteTime::format($record->published_on ?? $record->publish_at, 'd.m.Y') }}</p>
                <div>
                    <h2 class="notice-row__title"><a href="{{ \App\Support\Routing\PublicPath::toUrl($record->publicPath()) }}">{{ $record->displayTitle() }}</a></h2>
                    @if ($record->summary)<p class="notice-row__summary">{{ $record->summary }}</p>@endif
                    @if ($record->category)<p class="meta">{{ $record->category->name }}</p>@endif
                </div>
            </li>
        @endforeach
    </ul>
    @include('public.partials.pagination', ['paginator' => $records])
@endif
