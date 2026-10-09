@extends('layouts.public')
@section('title', 'Startseite')
@php
    $catalog = app(\App\Services\Content\PublicCatalog::class);
    $chips = array_slice($shortcuts, 0, 5);
    $hours = \App\Support\Content\PublicFormat::lines($townHall?->opening_hours);
@endphp
@section('content')
<section class="home-hero {{ $hero ? '' : 'home-hero--text' }}" aria-labelledby="home-heading">
    <div class="home-hero__main">
        <p class="eyebrow">Willkommen in der {{ $siteTitle }}</p>
        <h1 id="home-heading">Wie können wir Ihnen helfen?</h1>
        @include('public.partials.search-form', ['searchId' => 'home-search', 'pill' => true, 'searchLabel' => 'Anliegen oder Suchbegriff'])
        @if ($chips)
            <nav class="chip-list" aria-label="Häufige Anliegen">
                <ul>@foreach ($chips as $node)<li><a class="chip" href="{{ $node->href }}">{{ $node->item->label }}</a></li>@endforeach</ul>
            </nav>
        @endif
    </div>
    @if ($hero)
        <figure class="home-hero__media">
            @include('public.partials.image', ['medium' => $hero, 'imageSizes' => '(max-width: 64rem) calc(100vw - 2rem), 34rem', 'imageLoading' => 'eager', 'imageClass' => 'home-hero__image'])
            @if ($townHall)
                <figcaption class="home-hero__caption"><span>{{ $townHall->displayTitle() }} · {{ $townHall->street }}</span>@if ($hours)<span><x-icon name="clock" /> {{ $hours[0] }}</span>@endif</figcaption>
            @endif
        </figure>
    @elseif ($townHall)
        <aside class="home-hero__card" aria-label="{{ $townHall->displayTitle() }}">
            <p class="home-hero__card-title">{{ $townHall->displayTitle() }}</p>
            <p>{{ $townHall->street }}<br>{{ $townHall->postal_code }} {{ $townHall->city }}</p>
            @if ($hours)<ul class="plain-list">@foreach ($hours as $line)<li>{{ $line }}</li>@endforeach</ul>@endif
        </aside>
    @endif
</section>

@if ($greeting)
    <figure class="section greeting {{ $greeting['media'] ? '' : 'greeting--text' }}" aria-labelledby="greeting-text">
        @if ($greeting['media'])
            <div class="greeting__media">@include('public.partials.image', ['medium' => $greeting['media'], 'imageSizes' => '(max-width: 40rem) calc(100vw - 2rem), 9rem', 'imageClass' => 'greeting__image'])</div>
        @endif
        <div class="greeting__body">
            <blockquote class="greeting__quote" id="greeting-text">{!! \App\Support\Content\SafeMarkdown::toHtml($greeting['text']) !!}</blockquote>
            <figcaption class="greeting__caption">
                @if ($greeting['role'])<span>{{ $greeting['role'] }}</span>@endif
                @if ($greeting['name'])<strong class="greeting__name">{{ $greeting['name'] }}</strong>@endif
                @if ($greeting['url'])<span><a href="{{ \App\Support\Routing\PublicPath::toUrl($greeting['url']) }}">Zum Grußwort</a></span>@endif
            </figcaption>
        </div>
    </figure>
@endif

@if (config('public.homepage.services') && $shortcuts)
    <section class="section" aria-labelledby="tasks-heading">
        <div class="section-heading">
            <h2 id="tasks-heading">Häufig gesucht</h2>
            <a class="more-link" href="{{ $catalog->sectionPath('az') }}">Alle Leistungen A–Z <x-icon name="arrow-right" /></a>
        </div>
        <ul class="link-grid">
            @foreach ($shortcuts as $node)
                @php $target = $node->target; $service = $target instanceof \App\Models\Service ? $target : null; @endphp
                <li>
                    <a class="link-row" href="{{ $node->href }}">
                        <span class="link-row__text">
                            <span class="link-row__title">{{ $node->item->label }}@if ($service?->onlineService?->isPubliclyReachable() && in_array($service->online_service_mode, [\App\Enums\OnlineServiceMode::Application, \App\Enums\OnlineServiceMode::Appointment], true)) <span class="badge badge--online">Online</span>@endif</span>
                            @if ($service && ($department = $service->departments()->where('is_active', true)->first()))<span class="link-row__meta">{{ $department->name }}</span>
                            @elseif ($target instanceof \App\Models\Location && $target->opening_hours)<span class="link-row__meta">{{ implode(' · ', \App\Support\Content\PublicFormat::lines($target->opening_hours)) }}</span>@endif
                        </span>
                        <x-icon name="arrow-right" class="link-row__arrow" />
                    </a>
                </li>
            @endforeach
        </ul>
    </section>
@endif

@if ((config('public.homepage.news') && $news->isNotEmpty()) || (config('public.homepage.events') && $events->isNotEmpty()))
    <div class="home-columns section">
        @if (config('public.homepage.news'))
            <section aria-labelledby="news-heading">
                <div class="section-heading">
                    <h2 id="news-heading">Aktuelles</h2>
                    <a class="more-link" href="{{ $catalog->sectionPath('articles') }}">Alle Meldungen <x-icon name="arrow-right" /></a>
                </div>
                @if ($news->isEmpty())
                    <p class="empty-note">Zurzeit gibt es keine neuen Meldungen.</p>
                @else
                    <ul class="news-list">
                        @foreach ($news as $record)
                            <li>@include('public.partials.news-item', ['record' => $record, 'withImage' => $loop->first, 'headingTag' => 'h3'])</li>
                        @endforeach
                    </ul>
                @endif
            </section>
        @endif
        @if (config('public.homepage.events'))
            <section aria-labelledby="events-heading">
                <div class="section-heading">
                    <h2 id="events-heading">Veranstaltungen</h2>
                    <a class="more-link" href="{{ $catalog->sectionPath('events') }}">Alle Termine <x-icon name="arrow-right" /></a>
                </div>
                @if ($events->isEmpty())
                    <p class="empty-note">Zurzeit sind keine Veranstaltungen angekündigt.</p>
                @else
                    <ul class="event-list">
                        @foreach ($events as $record)
                            <li>@include('public.partials.event-item', ['record' => $record, 'headingTag' => 'h3'])</li>
                        @endforeach
                    </ul>
                @endif
            </section>
        @endif
    </div>
@endif

@if (config('public.homepage.online') && ($portal || $online->isNotEmpty()))
    <section class="section panel panel--split" aria-labelledby="online-heading">
        <div class="panel__intro">
            <h2 id="online-heading">Digitales Rathaus</h2>
            <p>Viele Anträge stellen Sie rund um die Uhr online – unabhängig von den Öffnungszeiten{{ $portal?->provider_name ? ', zum Beispiel im '.$portal->provider_name : '' }}.</p>
            @if ($portal)
                <a class="button button--pill" href="{{ $portal->url }}">{{ $portal->title }}<span class="visually-hidden"> (externer Link)</span> <x-icon name="external" /></a>
            @endif
        </div>
        @if ($online->isNotEmpty())
            <ul class="link-list">
                @foreach ($online as $link)
                    <li>
                        <a class="link-row" href="{{ $link->url }}">
                            <span class="link-row__text"><span class="link-row__title">{{ $link->title }}<span class="visually-hidden"> (externer Link)</span></span>@if ($link->provider_name)<span class="link-row__meta">{{ $link->provider_name }} · extern</span>@endif</span>
                            <x-icon name="external" class="link-row__arrow" />
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
@endif

@if (config('public.homepage.contact') && ($townHall || $central || $works || $recycling))
    <section class="section home-contact" aria-labelledby="contact-heading">
        <h2 id="contact-heading">Kontakt</h2>
        <div class="contact-columns">
            @if ($townHall || $central)
                <div>
                    <h3>{{ $townHall?->displayTitle() ?? $central->name }}</h3>
                    @if ($townHall)<p>{{ $townHall->street }}, {{ $townHall->postal_code }} {{ $townHall->city }}</p>@endif
                    @if ($central) @include('public.partials.contact-data', ['contact' => $central]) @endif
                    <p><a href="{{ route('public.contact') }}">Nachricht schreiben</a></p>
                </div>
            @endif
            @if ($hours)
                <div>
                    <h3>Öffnungszeiten</h3>
                    <ul class="plain-list">@foreach ($hours as $line)<li>{{ $line }}</li>@endforeach</ul>
                </div>
            @endif
            @if ($works || $recycling)
                <div>
                    <h3>{{ $works?->name ?? $recycling->displayTitle() }}@if ($works && $recycling) und {{ $recycling->displayTitle() }}@endif</h3>
                    @if ($recycling)
                        <p>{{ $recycling->street }}</p>
                        @if ($recycling->opening_hours)<p class="meta">{{ $recycling->displayTitle() }}: {{ implode(' · ', \App\Support\Content\PublicFormat::lines($recycling->opening_hours)) }}</p>@endif
                    @endif
                    @if ($works) @include('public.partials.contact-data', ['contact' => $works]) @endif
                </div>
            @endif
        </div>
    </section>
@endif
@endsection
