<h2 class="catalog-search-heading">Bekanntmachungen finden</h2>
<p class="meta">Suchen Sie nach einem Titel oder wählen Sie ein Thema. Die PDF-Dokumente sind direkt beim jeweiligen Eintrag verlinkt.</p>
@include('public.catalog.filters', ['searchLabel' => 'Bekanntmachungen durchsuchen'])
@include('public.catalog.results-count', ['singular' => 'Bekanntmachung', 'plural' => 'Bekanntmachungen'])
@if ($records->isEmpty())
    <div class="empty-state"><h2>Keine Bekanntmachungen gefunden</h2><p>{{ $filtered ? 'Bitte ändern Sie die Filter.' : 'Zurzeit sind keine Bekanntmachungen veröffentlicht.' }}</p></div>
@else
    <ul class="notice-list">
        @foreach ($records as $record)
            <li class="notice-row">
                <p class="notice-row__date"><span class="visually-hidden">{{ $record->published_on ? 'Bekannt gemacht am' : 'Veröffentlicht am' }} </span>{{ \App\Support\SiteTime::format($record->published_on ?? $record->publish_at, 'd.m.Y') }}</p>
                <div>
                    <h2 class="notice-row__title"><a href="{{ \App\Support\Routing\PublicPath::toUrl($record->publicPath()) }}">{{ $record->displayTitle() }}</a></h2>
                    @if ($record->summary)<p class="notice-row__summary">{{ $record->summary }}</p>@endif
                    @php $noticeFiles = $record->blocks->where('type', 'downloads')->map->referenced()->filter(fn ($document) => $document && $document->isPubliclyReachable() && app(\App\Services\Content\DocumentStorage::class)->exists($document)); @endphp
                    @if ($noticeFiles->isNotEmpty())<ul class="notice-files plain-list">@foreach ($noticeFiles as $document)<li><a href="{{ \App\Support\Routing\PublicPath::toUrl($document->downloadPath()) }}"><x-icon name="download" /><span>PDF öffnen <span class="meta">· {{ \App\Support\Content\PublicFormat::fileSize($document->size_bytes) }}</span><span class="visually-hidden">: {{ $document->title }}</span></span></a></li>@endforeach</ul>@endif
                    @if ($record->category)<p class="meta">{{ $record->category->name }}</p>@endif
                </div>
            </li>
        @endforeach
    </ul>
    @include('public.partials.pagination', ['paginator' => $records])
@endif
