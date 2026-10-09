@php $byCategory = $records->getCollection()->groupBy(fn ($o) => $o->category?->name ?? 'Weitere')->sortKeys(); @endphp
@include('public.catalog.filters', ['searchLabel' => 'Name oder Stichwort'])
@include('public.catalog.results-count', ['singular' => 'Eintrag', 'plural' => 'Einträge'])
@if ($records->isEmpty())
    <div class="empty-state"><h2>Keine Einträge gefunden</h2><p>{{ $filtered ? 'Bitte ändern Sie die Filter.' : 'Zurzeit sind keine Vereine oder Organisationen eingetragen.' }}</p></div>
@else
    @foreach ($byCategory as $name => $items)
        <section class="directory-section" aria-labelledby="org-{{ $loop->index }}">
            <h2 id="org-{{ $loop->index }}">{{ $name }}</h2>
            <ul class="card-grid">
                @foreach ($items as $record)
                    @php $path = $record->publicPath(); @endphp
                    <li class="directory-card">
                        <h3 class="directory-card__title">@if ($path)<a href="{{ \App\Support\Routing\PublicPath::toUrl($path) }}">{{ $record->displayTitle() }}</a>@else{{ $record->displayTitle() }}@endif</h3>
                        <p class="meta">{{ $record->type?->label() }}</p>
                        @if ($record->description)<p class="directory-card__text">{{ \Illuminate\Support\Str::limit($record->description, 140) }}</p>@endif
                        @include('public.partials.contact-data', ['contact' => $record])
                        @if ($record->website)<p class="directory-card__link"><a href="{{ $record->website }}">Website<span class="visually-hidden"> von {{ $record->name }} (externer Link)</span><x-icon name="external" class="icon--inline" /></a></p>@endif
                    </li>
                @endforeach
            </ul>
        </section>
    @endforeach
@endif
