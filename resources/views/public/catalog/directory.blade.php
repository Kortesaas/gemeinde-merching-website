@php $directory = \App\Http\Controllers\Public\CatalogController::directoryGroups($records->getCollection()); $slug = fn ($label) => \Illuminate\Support\Str::slug($label); @endphp
@include('public.catalog.filters', ['searchLabel' => 'Name oder Einrichtung'])
@if ($directory === [])
    <div class="empty-state"><h2>Keine Einträge gefunden</h2><p>Bitte ändern Sie den Suchbegriff.</p></div>
@else
    <nav class="jump-links" aria-label="Bereiche dieser Seite"><ul>@foreach ($directory as $label => $items)<li><a href="#{{ $slug($label) }}">{{ $label }} <span class="meta">{{ $items->count() }}</span></a></li>@endforeach</ul></nav>
    @foreach ($directory as $label => $items)
        <section class="directory-section" id="{{ $slug($label) }}" aria-labelledby="{{ $slug($label) }}-heading">
            <h2 id="{{ $slug($label) }}-heading">{{ $label }}</h2>
            @if ($items->first() instanceof \App\Models\Person)
                <ul class="person-list person-list--wide">
                    @foreach ($items as $person)
                        <li>
                            <span class="person-list__name">{{ $person->displayTitle() }}</span>
                            <span class="person-list__meta">{{ $person->job_title }}@if ($person->departments->isNotEmpty()) · {{ $person->departments->pluck('name')->implode(', ') }}@endif</span>
                            <span class="person-list__contact">@if ($person->phone)<a href="{{ \App\Support\Content\PublicFormat::phoneHref($person->phone) }}"><span class="visually-hidden">Telefon: </span>{{ $person->phone }}</a>@endif @if ($person->email)<a href="mailto:{{ $person->email }}"><span class="visually-hidden">E-Mail: </span>{{ $person->email }}</a>@endif</span>
                        </li>
                    @endforeach
                </ul>
            @else
                <ul class="card-grid">
                    @foreach ($items as $record)
                        @php $path = method_exists($record, 'publicPath') ? $record->publicPath() : null; @endphp
                        <li class="directory-card">
                            <h3 class="directory-card__title">@if ($path)<a href="{{ \App\Support\Routing\PublicPath::toUrl($path) }}">{{ $record->displayTitle() }}</a>@else{{ $record->displayTitle() }}@endif</h3>
                            @if ($record instanceof \App\Models\Organization)<p class="meta">{{ $record->type?->label() }}@if ($record->category) · {{ $record->category->name }}@endif</p>@endif
                            @if ($record instanceof \App\Models\Location)<p class="meta">{{ $record->type?->label() }}</p>@if ($record->street)<p>{{ $record->street }}, {{ $record->postal_code }} {{ $record->city }}</p>@endif @endif
                            @if ($record instanceof \App\Models\CouncilTerm)<p class="meta">{{ $record->is_historical ? 'Historische Wahlperiode' : 'Aktuelle Wahlperiode' }}</p>@endif
                            @if (($record instanceof \App\Models\Department || $record instanceof \App\Models\Organization) && $record->description)<p class="directory-card__text">{{ \Illuminate\Support\Str::limit($record->description, 140) }}</p>@endif
                            @if (! $record instanceof \App\Models\CouncilTerm) @include('public.partials.contact-data', ['contact' => $record]) @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    @endforeach
@endif
