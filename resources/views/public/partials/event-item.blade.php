@php
    $record->loadMissing(['location', 'canonicalRoute', 'category']);
    $start = \App\Support\SiteTime::fromUtc($record->starts_at)->locale('de');
    $cancelled = $record->operational_status === \App\Enums\EventOperationalStatus::Cancelled;
    $tag = $headingTag ?? 'h2';
    $place = $record->location?->isPubliclyReachable() ? $record->location->displayTitle() : $record->venue;
@endphp
<article class="event-item {{ $cancelled ? 'event-item--cancelled' : '' }}">
    <p class="date-badge" aria-hidden="true"><span class="date-badge__day">{{ $start->format('d') }}</span><span class="date-badge__month">{{ $start->translatedFormat('M') }}</span></p>
    <div class="event-item__body">
        <{{ $tag }} class="event-item__title"><a href="{{ \App\Support\Routing\PublicPath::toUrl((string) $record->publicPath()) }}">{{ $record->displayTitle() }}</a></{{ $tag }}>
        <p class="meta">
            <time datetime="{{ $record->time_is_unspecified ? $start->format('Y-m-d') : $record->starts_at->toIso8601String() }}">{{ $start->translatedFormat('l, j. F') }}@if ($record->time_is_unspecified), {{ $record->time_text ?: 'Uhrzeit nicht angegeben' }} @elseif (! $record->all_day), {{ $start->format('H:i') }} Uhr @else, ganztägig @endif</time>@if ($place) · {{ $place }}@endif
        </p>
        @if ($cancelled)<p class="badge badge--cancelled">Abgesagt</p>
        @elseif ($record->schedule_notice)<p class="badge badge--changed">Terminänderung</p>@endif
    </div>
</article>
