@php
    $months = $records->getCollection()->groupBy(fn ($e) => \App\Support\SiteTime::formatLocalized($e->starts_at, 'F Y'));
    $onPage = $records->getCollection()->keyBy('id');
@endphp
@include('public.catalog.filters', ['searchLabel' => 'Veranstaltungen durchsuchen'])
<div class="events-layout">
    <div class="events-layout__list">
        @include('public.catalog.results-count', ['singular' => $archive ? 'vergangene Veranstaltung' : 'Veranstaltung', 'plural' => $archive ? 'vergangene Veranstaltungen' : 'Veranstaltungen'])
        @if ($records->isEmpty())
            <div class="empty-state"><h2>{{ $archive ? 'Keine vergangenen Veranstaltungen' : 'Keine anstehenden Veranstaltungen' }}</h2><p>{{ $filtered ? 'Bitte ändern Sie die Filter.' : 'Sobald neue Termine feststehen, finden Sie sie hier.' }}</p>@unless ($archive)<a href="?archiv=1">Vergangene Veranstaltungen ansehen</a>@endunless</div>
        @else
            @foreach ($months as $month => $events)
                <section class="month-group" aria-labelledby="month-{{ $loop->index }}">
                    <h2 id="month-{{ $loop->index }}" class="month-group__title">{{ $month }}</h2>
                    <ul class="event-list event-list--wide">
                        @foreach ($events as $record)
                            <li id="termin-{{ $record->id }}" data-event-dates="{{ implode(' ', \App\Support\Content\EventCalendar::dates($record)) }}">@include('public.partials.event-item', ['record' => $record, 'headingTag' => 'h3'])</li>
                        @endforeach
                    </ul>
                </section>
            @endforeach
            @include('public.partials.pagination', ['paginator' => $records])
        @endif
    </div>
    @include('public.partials.event-calendar', ['calendar' => $calendar, 'onPage' => $onPage])
</div>
