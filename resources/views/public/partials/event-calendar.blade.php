{{-- Visual month overview next to the event list. The list stays the primary,
     complete presentation; the calendar is a navigational aid. --}}
@php
    $month = $calendar['month']->locale('de');
    $title = $month->translatedFormat('F Y');
    $monthUrl = fn (string $value) => request()->fullUrlWithQuery(['monat' => $value, 'page' => null]).'#kalender';
    $weekdays = ['Mo' => 'Montag', 'Di' => 'Dienstag', 'Mi' => 'Mittwoch', 'Do' => 'Donnerstag', 'Fr' => 'Freitag', 'Sa' => 'Samstag', 'So' => 'Sonntag'];
@endphp
<aside class="event-calendar" id="kalender" aria-labelledby="kalender-titel" data-event-calendar>
    <div class="event-calendar__head">
        <a class="event-calendar__nav" href="{{ $monthUrl($calendar['previous']) }}" rel="nofollow" data-cal-nav="previous"><x-icon name="arrow-left" /><span class="visually-hidden">Vorheriger Monat</span></a>
        <h2 id="kalender-titel" class="event-calendar__title">{{ $title }}</h2>
        <a class="event-calendar__nav" href="{{ $monthUrl($calendar['next']) }}" rel="nofollow" data-cal-nav="next"><x-icon name="arrow-right" /><span class="visually-hidden">Nächster Monat</span></a>
    </div>
    <table class="event-calendar__grid">
        <caption class="visually-hidden">Kalender {{ $title }}: {{ $calendar['count'] === 0 ? 'keine Veranstaltungen' : 'Tage mit Veranstaltungen sind verlinkt' }}</caption>
        <thead><tr>@foreach ($weekdays as $short => $long)<th scope="col"><abbr title="{{ $long }}">{{ $short }}</abbr></th>@endforeach</tr></thead>
        <tbody>
            @foreach ($calendar['weeks'] as $week)
                <tr>
                    @foreach ($week as $day)
                        @php
                            $events = $day['events'];
                            $first = $events[0] ?? null;
                            $date = \Carbon\CarbonImmutable::parse($day['date'])->locale('de');
                            $href = $first ? ($onPage->has($first->id) ? '#termin-'.$first->id : \App\Support\Routing\PublicPath::toUrl((string) $first->publicPath())) : null;
                        @endphp
                        <td class="{{ $day['inMonth'] ? '' : 'is-outside' }} {{ $day['today'] ? 'is-today' : '' }} {{ $events ? 'has-events' : '' }}" @if ($events) data-cal-date="{{ $day['date'] }}" @endif>
                            @if ($events)
                                <a class="event-calendar__day" href="{{ $href }}" aria-describedby="kal-{{ $day['date'] }}">
                                    {{ $day['day'] }}<span class="visually-hidden">. {{ $date->translatedFormat('F') }}</span>
                                    <span class="event-calendar__dots" aria-hidden="true">@foreach (array_slice($events, 0, 3) as $event)<span class="{{ $event->operational_status === \App\Enums\EventOperationalStatus::Cancelled ? 'is-cancelled' : '' }}"></span>@endforeach</span>
                                </a>
                                <div class="event-calendar__popover" id="kal-{{ $day['date'] }}" role="tooltip">
                                    <p class="event-calendar__popover-date">{{ $date->translatedFormat('l, j. F') }}</p>
                                    <ul>
                                        @foreach ($events as $event)
                                            <li>@unless ($event->all_day)<time>{{ \App\Support\SiteTime::format($event->starts_at, 'H:i') }}</time>@endunless {{ $event->displayTitle() }}@if ($event->operational_status === \App\Enums\EventOperationalStatus::Cancelled) <strong>(abgesagt)</strong>@endif</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @else
                                {{-- Days of neighbouring months stay empty; the grid keeps six rows. --}}
                                <span class="event-calendar__day" @if (! $day['inMonth']) aria-hidden="true" @endif>{{ $day['inMonth'] ? $day['day'] : '' }}</span>
                            @endif
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
    {{-- One line in both cases, so the calendar keeps its height while switching months. --}}
    <p class="event-calendar__legend">@if ($calendar['count'] > 0)<span class="event-calendar__legend-dot" aria-hidden="true"></span> Tag mit Veranstaltung @else Keine Termine in diesem Monat @endif</p>
</aside>
