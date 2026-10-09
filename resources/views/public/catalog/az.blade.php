@php
    $alphabet = range('A', 'Z');
    $present = $groups->keys()->all();
    $index = array_flip(array_values($present));
@endphp
@include('public.catalog.filters', ['filterLabel' => 'Leistungen filtern', 'searchLabel' => 'Anliegen oder Stichwort'])
<nav class="alphabet" aria-label="Anfangsbuchstaben">
    <ul>
        @foreach ($alphabet as $letter)
            <li>@if (isset($index[$letter]))<a href="#letter-{{ $index[$letter] }}">{{ $letter }}</a>@else<span aria-hidden="true">{{ $letter }}</span>@endif</li>
        @endforeach
        @if (isset($index['#']))<li><a href="#letter-{{ $index['#'] }}">#</a></li>@endif
    </ul>
</nav>
@if ($groups->isEmpty())
    <div class="empty-state"><h2>Keine Leistungen gefunden</h2><p>Bitte prüfen Sie die Schreibweise oder setzen Sie die Filter zurück.</p><a href="{{ url()->current() }}">Alle Leistungen anzeigen</a></div>
@endif
@foreach ($groups as $letter => $group)
    <section class="az-section" id="letter-{{ $index[$letter] }}" aria-labelledby="letter-heading-{{ $index[$letter] }}">
        <span id="buchstabe-{{ strtolower($letter) }}" class="az-anchor"></span>
        <h2 id="letter-heading-{{ $index[$letter] }}" class="az-letter">{{ $letter }}</h2>
        <ul class="az-list">
            @foreach ($group as $record)
                @php $department = $record->departments->firstWhere('is_active', true); $people = $record->contacts->where('is_active', true); @endphp
                <li class="az-row">
                    <h3 class="az-row__title"><a href="{{ \App\Support\Routing\PublicPath::toUrl($record->publicPath()) }}">{{ $record->displayTitle() }}</a>@if ($record->onlineService?->isPubliclyReachable() && in_array($record->online_service_mode, [\App\Enums\OnlineServiceMode::Application, \App\Enums\OnlineServiceMode::Appointment], true)) <span class="badge badge--online">Online</span>@endif</h3>
                    <p class="az-row__who">@if ($department){{ $department->name }}@else<span class="meta">Zuständigkeit siehe Leistungsseite</span>@endif @if ($people->isNotEmpty())<span class="az-row__people">{{ $people->map->displayTitle()->implode(', ') }}</span>@endif</p>
                    <p class="az-row__phone">@if ($department?->phone)<a href="{{ \App\Support\Content\PublicFormat::phoneHref($department->phone) }}"><span class="visually-hidden">Telefon: </span>{{ $department->phone }}</a>@endif</p>
                </li>
            @endforeach
        </ul>
    </section>
@endforeach
