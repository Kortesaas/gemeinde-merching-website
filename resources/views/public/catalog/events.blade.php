@php
    $onPage = $records->getCollection()->keyBy('id');
    $monthTitle = $calendar['month']->locale('de')->translatedFormat('F Y');
    $monthUrl = fn (string $value) => request()->fullUrlWithQuery(['monat' => $value, 'page' => null, 'archiv' => null]);
@endphp
@include('public.catalog.filters', ['searchLabel' => 'Veranstaltungen im ausgewählten Monat durchsuchen'])
<div data-events-view>
    <nav class="event-month-navigation" aria-label="Veranstaltungsmonat wählen">
        <a class="event-calendar__nav" href="{{ $monthUrl($calendar['previous']) }}" data-month-nav="previous"><x-icon name="arrow-left" /><span class="visually-hidden">Vorheriger Monat</span></a>
        <h2 id="event-month-title">{{ $monthTitle }}</h2>
        <a class="event-calendar__nav" href="{{ $monthUrl($calendar['next']) }}" data-month-nav="next"><x-icon name="arrow-right" /><span class="visually-hidden">Nächster Monat</span></a>
    </nav>
    <div class="events-layout">
        <section class="events-layout__list" aria-labelledby="event-month-title">
            @include('public.catalog.results-count', ['singular' => 'Veranstaltung', 'plural' => 'Veranstaltungen'])
            @if ($records->isEmpty())
                <div class="empty-state"><h3>Keine Veranstaltungen {{ $filtered ? 'gefunden' : 'in diesem Monat' }}</h3><p>{{ $filtered ? 'Bitte ändern Sie die Filter oder wählen Sie einen anderen Monat.' : 'Wählen Sie mit den Pfeilen einen anderen Monat.' }}</p></div>
            @else
                <ul class="event-list event-list--wide">
                    @foreach ($records as $record)
                        <li id="termin-{{ $record->id }}" data-event-dates="{{ implode(' ', \App\Support\Content\EventCalendar::dates($record, $calendar['month'])) }}">@include('public.partials.event-item', ['record' => $record, 'headingTag' => 'h3'])</li>
                    @endforeach
                </ul>
            @endif
        </section>
        <details class="event-calendar-panel" open data-calendar-disclosure>
            <summary>Monatskalender <x-icon name="chevron-down" /></summary>
            @include('public.partials.event-calendar', ['calendar' => $calendar, 'onPage' => $onPage])
        </details>
    </div>
</div>
