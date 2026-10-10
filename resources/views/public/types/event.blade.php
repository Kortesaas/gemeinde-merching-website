@php
    $model->loadMissing(['category', 'location.mapResource', 'organization', 'contactPerson']);
    $start = \App\Support\SiteTime::fromUtc($model->starts_at)->locale('de');
    $end = $model->ends_at ? \App\Support\SiteTime::fromUtc($model->ends_at)->locale('de') : null;
    $cancelled = $model->operational_status === \App\Enums\EventOperationalStatus::Cancelled;
    $past = $model->endsAtForArchiving()->lessThanOrEqualTo(now()) || $model->isInPublicArchive();
    $location = $model->location?->isPubliclyReachable() ? $model->location : null;
    $organizer = $model->organization?->isPubliclyReachable() ? $model->organization : null;
    $contact = $model->contactPerson?->isPubliclyReachable() ? $model->contactPerson : null;
    $sameDay = $end && $end->isSameDay($start);
@endphp
@include('public.partials.page-header', ['eyebrow' => 'Veranstaltung'.($model->category ? ' · '.$model->category->name : ''), 'lead' => null])
@if ($cancelled)
    <p class="notice-box notice-box--error"><x-icon name="error" /> <span><strong>Abgesagt</strong> – Diese Veranstaltung findet nicht statt.@if ($model->schedule_notice) {{ $model->schedule_notice }}@endif</span></p>
@elseif ($model->schedule_notice)
    <p class="notice-box notice-box--warning"><x-icon name="warning" /> <span><strong>Terminänderung:</strong> {{ $model->schedule_notice }}</span></p>
@endif
@if ($past)<p class="notice-box"><x-icon name="history" /> Archiv: Diese Veranstaltung liegt in der Vergangenheit.</p>@endif
<div class="content-layout">
    <div class="content-main">
        <dl class="fact-strip fact-strip--event {{ $cancelled ? 'is-cancelled' : '' }}">
            <div><dt>Datum</dt><dd><strong class="fact-strip__value">{{ $start->translatedFormat('j. F Y') }}</strong><span>{{ $start->translatedFormat('l') }}@if ($end && ! $sameDay && ! ($model->all_day && $end->copy()->subDay()->isSameDay($start))) bis {{ ($model->all_day ? $end->copy()->subSecond() : $end)->translatedFormat('l, j. F') }}@endif</span></dd></div>
            <div><dt>Uhrzeit</dt><dd><strong class="fact-strip__value">@if ($model->time_is_unspecified) {{ $model->time_text ?: 'Nicht angegeben' }} @elseif ($model->all_day) ganztägig @else {{ $start->format('H:i') }}@if ($end && $sameDay)–{{ $end->format('H:i') }}@endif Uhr @endif</strong></dd></div>
            @if ($location || $model->venue)<div><dt>Ort</dt><dd><strong class="fact-strip__value fact-strip__value--text">{{ $location?->displayTitle() ?? $model->venue }}</strong>@if ($location?->street)<span>{{ $location->street }}, {{ $location->city }}</span>@endif</dd></div>@endif
        </dl>
        @if (($model->registration_url || $model->url) && ! $cancelled && ! $past)
            <div class="action-row">
                @if ($model->registration_url)<a class="button button--pill" href="{{ $model->registration_url }}">Zur Anmeldung<span class="visually-hidden"> (externer Link)</span> <x-icon name="external" /></a>@endif
                @if ($model->url)<a class="action-link" href="{{ $model->url }}">Weitere Informationen des Veranstalters<span class="visually-hidden"> (externer Link)</span> <x-icon name="external" /></a>@endif
            </div>
        @endif
        @if ($model->description)<div class="prose">{!! \App\Support\Content\SafeMarkdown::toHtml($model->description) !!}</div>@endif
        @if ($model->remarks)<p class="callout callout--plain">{{ $model->remarks }}</p>@endif
        @include('public.partials.blocks')
        @include('public.partials.downloads', ['owner' => $model])
        @include('public.partials.links', ['owner' => $model])
    </div>
    <aside class="content-aside" aria-label="Ort und Veranstalter">
        @if ($location)
            <h2 class="aside-heading">Veranstaltungsort</h2>
            @include('public.partials.location', ['location' => $location, 'showHours' => false])
        @elseif ($model->venue)
            <h2 class="aside-heading">Veranstaltungsort</h2><p>{{ $model->venue }}</p>
        @endif
        @if ($organizer || $model->organizer_name)
            <div class="aside-section">
                <h2 class="aside-heading">Veranstalter</h2>
                @if ($organizer)
                    <p class="contact-card__name">@if ($organizer->publicPath())<a href="{{ \App\Support\Routing\PublicPath::toUrl($organizer->publicPath()) }}">{{ $organizer->name }}</a>@else{{ $organizer->name }}@endif</p>
                    @include('public.partials.contact-data', ['contact' => $organizer])
                @else<p>{{ $model->organizer_name }}</p>@endif
            </div>
        @endif
        @if ($contact)
            <div class="aside-section"><h2 class="aside-heading">Ansprechperson</h2>@include('public.partials.contact-card', ['contact' => $contact])</div>
        @endif
    </aside>
</div>
